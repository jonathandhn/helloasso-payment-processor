#!/usr/bin/env python3
import os
import re
import ast
import struct
import unicodedata
from datetime import datetime, timezone

PROJECT_VERSION = "helloasso-payment-processor 2.0.0"
TRANSLATOR = "Jonathan Dahan <jonathan@dhn.one>"

def build_header(language, is_template=False):
    timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M+0000")
    lines = [
        f"Project-Id-Version: {PROJECT_VERSION}",
    ]
    if is_template:
        lines.append(f"POT-Creation-Date: {timestamp}")
    lines.extend([
        f"PO-Revision-Date: {timestamp}",
        f"Last-Translator: {TRANSLATOR}",
        f"Language-Team: {language or 'Translations'}",
        f"Language: {language}",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
    ])
    return "\n".join(lines) + "\n"

def normalize(s):
    # Remove accents, punctuation, apostrophes, and spaces for matching
    s = s.strip()
    s = "".join(c for c in unicodedata.normalize("NFD", s) if not unicodedata.combining(c))
    return re.sub(r"[^a-zA-Z0-9]", "", s).lower()

def escape_str(s):
    res = []
    for c in s:
        if c == '"':
            res.append('\\"')
        elif c == '\\':
            res.append('\\\\')
        elif c == '\n':
            res.append('\\n')
        elif c == '\t':
            res.append('\\t')
        elif c == '\r':
            res.append('\\r')
        else:
            res.append(c)
    return "".join(res)

def format_po_str(label, s):
    if '\n' in s:
        lines = s.split('\n')
        out = f'{label} ""\n'
        for i, line in enumerate(lines):
            escaped_line = escape_str(line)
            if i < len(lines) - 1:
                out += f'"{escaped_line}\\n"\n'
            else:
                out += f'"{escaped_line}"\n'
        return out
    else:
        escaped = escape_str(s)
        return f'{label} "{escaped}"\n'

def parse_po_str(s):
    parts = []
    for line in s.strip().split('\n'):
        line = line.strip()
        if line.startswith('"') and line.endswith('"'):
            parts.append(ast.literal_eval(line))
    return "".join(parts)

def parse_po(po_path):
    translations = {}
    if not os.path.exists(po_path):
        return translations
        
    with open(po_path, "r", encoding="utf-8") as f:
        content = f.read()
        
    entry_pattern = re.compile(
        r'(?:^|\n\n)(?P<comments>(?:[ \t]*#.*\n)*)'
        r'[ \t]*msgid\s+(?P<msgid>""|"(?:[^"\\]|\\.)*"(?:\s*"(?:[^"\\]|\\.)*")*)\s*'
        r'msgstr\s+(?P<msgstr>""|"(?:[^"\\]|\\.)*"(?:\s*"(?:[^"\\]|\\.)*")*)',
        re.MULTILINE
    )
    
    for m in entry_pattern.finditer(content):
        msgid = parse_po_str(m.group('msgid'))
        msgstr = parse_po_str(m.group('msgstr'))
        comments = m.group('comments').strip()
        
        translations[msgid] = {
            'msgstr': msgstr,
            'comments': comments
        }
        
    return translations

def write_po(po_dict, output_path, header_comments=""):
    with open(output_path, "w", encoding="utf-8") as f:
        if header_comments:
            f.write(header_comments + "\n\n")
        else:
            f.write("# Translation file\n\n")
            
        if "" in po_dict:
            f.write('msgid ""\n')
            header_str = po_dict[""]["msgstr"]
            f.write('msgstr ""\n')
            for line in header_str.split("\n"):
                if line:
                    f.write(f'"{line}\\n"\n')
            f.write("\n")
            
        for msgid in sorted(po_dict.keys()):
            if msgid == "":
                continue
            entry = po_dict[msgid]
            if entry.get('comments'):
                f.write(entry['comments'] + "\n")
            f.write(format_po_str("msgid", msgid))
            f.write(format_po_str("msgstr", entry['msgstr']))
            f.write("\n")

def compile_mo(po_dict, output_path):
    # Keep msgid "" in the MO: gettext uses it for charset and language metadata.
    po_dict = {k: v["msgstr"] for k, v in po_dict.items() if v.get("msgstr")}
    sorted_keys = sorted(po_dict.keys(), key=lambda s: s.encode('utf-8'))
    
    offsets = []
    original_data = b""
    translation_data = b""
    
    for key in sorted_keys:
        val = po_dict[key]
        key_bytes = key.encode('utf-8') + b'\x00'
        val_bytes = val.encode('utf-8') + b'\x00'
        
        offsets.append((len(key_bytes) - 1, len(original_data), len(val_bytes) - 1, len(translation_data)))
        
        original_data += key_bytes
        translation_data += val_bytes
        
    num_strings = len(sorted_keys)
    orig_table_offset = 28
    trans_table_offset = orig_table_offset + num_strings * 8
    orig_strings_base = trans_table_offset + num_strings * 8
    trans_strings_base = orig_strings_base + len(original_data)
    
    header = struct.pack("<Iiiiiii",
        0x950412de,        # Magic number
        0,                 # Version
        num_strings,       # Number of entries
        orig_table_offset, # Start of key index
        trans_table_offset,# Start of value index
        0,                 # Size of hash table
        0                  # Offset of hash table
    )
    
    orig_table = b""
    trans_table = b""
    
    for len_orig, off_orig, len_trans, off_trans in offsets:
        orig_table += struct.pack("<ii", len_orig, orig_strings_base + off_orig)
        trans_table += struct.pack("<ii", len_trans, trans_strings_base + off_trans)
        
    with open(output_path, "wb") as f:
        f.write(header)
        f.write(orig_table)
        f.write(trans_table)
        f.write(original_data)
        f.write(translation_data)

def extract_strings(root_dir):
    strings = set()
    regex = re.compile(r'\bE?::ts\(\s*(?:\'((?:[^\'\\]|\\.)*)\'|"((?:[^"\\]|\\.)*)")\s*(?:,|\))')
    
    for dirpath, _, filenames in os.walk(root_dir):
        if any(ignored in dirpath for ignored in [".git", "tests", "l10n", "vendor"]):
            continue
        for filename in filenames:
            if filename.endswith(".php"):
                path = os.path.join(dirpath, filename)
                with open(path, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
                for m in regex.finditer(content):
                    val = m.group(1) or m.group(2)
                    # Decode single or double quotes escapes
                    if m.group(1):
                        val = val.replace("\\'", "'")
                    else:
                        val = val.replace('\\"', '"')
                    strings.add(val)
    return strings

def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    print(f"Project root: {root}")
    
    extracted = extract_strings(root)
    print(f"Extracted {len(extracted)} strings from source files.")
    
    l10n_dir = os.path.join(root, "l10n")
    os.makedirs(l10n_dir, exist_ok=True)
    
    # Process French
    fr_po_path = os.path.join(l10n_dir, "fr_FR.po")
    fr_mo_path = os.path.join(l10n_dir, "fr_FR.mo")
    old_fr = parse_po(fr_po_path)
    print(f"Loaded {len(old_fr)} entries from fr_FR.po")
    
    # Build normalizer map for mapping old msgids to new ones
    norm_to_old_fr = {}
    for msgid, entry in old_fr.items():
        if msgid != "":
            norm_to_old_fr[normalize(msgid)] = (msgid, entry)
            
    fallback_translations_fr = {
        "HelloAsso payment is temporarily unavailable: HelloAsso connection must be restored by an administrator.": "Le paiement HelloAsso est temporairement indisponible : la connexion HelloAsso doit être rétablie par un administrateur.",
        "HelloAsso API error (%1)": "Erreur API HelloAsso (%1)",
        "instead of": "au lieu de",
        "Domain mismatch detected: this CiviCRM instance runs on a different host than the one authorized for the HelloAsso link: %1. OAuth callbacks will not function correctly on this staging/dev instance.": "Un décalage de domaine a été détecté : cette instance CiviCRM tourne sur un hôte différent de celui utilisé pour la liaison HelloAsso de : %1. Les redirections et retours OAuth ne fonctionneront pas correctement sur cette instance de test/staging.",
        "HelloAsso: Domain Mismatch (Staging/Dev Instance)": "HelloAsso : Décalage de domaine (Instance de Test/Staging)",
        "The HelloAsso webhook registered for %1 points to a different domain. Inbound payments will not be processed on this CiviCRM instance: %2.": "Le webhook enregistré pour %1 pointe vers un domaine différent de cette instance. Les notifications de paiement HelloAsso ne seront pas reçues ni traitées sur ce CiviCRM : %2.",
        "HelloAsso: Webhook Domain Mismatch": "HelloAsso : Décalage de domaine du Webhook"
    }
            
    new_fr = {
        "": {
            "msgstr": build_header("fr_FR"),
            "comments": old_fr.get("", {}).get("comments", ""),
        }
    }
        
    for msgid in extracted:
        norm_key = normalize(msgid)
        if msgid in old_fr:
            new_fr[msgid] = old_fr[msgid]
        elif msgid in fallback_translations_fr:
            new_fr[msgid] = {
                'msgstr': fallback_translations_fr[msgid],
                'comments': "#. Converted from French to English in source code"
            }
        elif norm_key in norm_to_old_fr:
            old_msgid, entry = norm_to_old_fr[norm_key]
            # Map existing translation to the corrected msgid!
            new_fr[msgid] = {
                'msgstr': entry['msgstr'],
                'comments': f"#. Corrected from old msgid: {old_msgid}\n{entry['comments']}".strip()
            }
            print(f"Mapped translation for: '{old_msgid}' -> '{msgid}'")
        else:
            # Check if French is default
            # (If it's French already, default translation is itself)
            is_french = any(c in msgid.lower() for c in ['é', 'è', 'à', 'ù', 'ç', 'ê', 'î', 'ô']) or "l'" in msgid.lower() or "d'" in msgid.lower()
            new_fr[msgid] = {
                'msgstr': msgid if is_french else "",
                'comments': ""
            }
            
    header_fr = old_fr.get("", {}).get("comments", "# Translation of HelloAsso Payment Processor in French")
    write_po(new_fr, fr_po_path, header_fr)
    compile_mo(new_fr, fr_mo_path)
    print(f"Synchronized and compiled fr_FR.po/mo successfully!")

    # Process English (for default english fallback of public-facing strings)
    en_po_path = os.path.join(l10n_dir, "en_US.po")
    en_mo_path = os.path.join(l10n_dir, "en_US.mo")
    old_en = parse_po(en_po_path)
    
    public_translations_en = {
        "Contribution en ligne": "Online contribution",
        "Contribution en ligne : %1": "Online contribution: %1",
        "Vous serez redirigé vers HelloAsso pour effectuer le paiement.": "You will be redirected to HelloAsso to complete your payment.",
        
        # Form validation errors
        "Le nom/prénom doit contenir au moins 3 caractères (règle HelloAsso).": "First/last name must contain at least 3 characters (HelloAsso rule).",
        "Le nom/prénom ne doit pas contenir 3 caractères répétitifs (règle HelloAsso).": "First/last name must not contain 3 repetitive characters (HelloAsso rule).",
        "Le nom/prénom ne doit pas contenir de chiffres (règle HelloAsso).": "First/last name must not contain numbers (HelloAsso rule).",
        "Le nom/prénom doit contenir au moins une voyelle (règle HelloAsso).": "First/last name must contain at least one vowel (HelloAsso rule).",
        "Cette valeur n'est pas autorisée par HelloAsso.": "This value is not allowed by HelloAsso.",
        "Le nom/prénom contient des caractères spéciaux non autorisés (règle HelloAsso).": "First/last name contains unauthorized special characters (HelloAsso rule).",
        "Le nom et le prénom ne doivent pas être identiques (règle HelloAsso).": "First name and last name must not be identical (HelloAsso rule).",
        
        # Core API validation errors
        "Le %1 doit contenir au moins 3 caractères (règle HelloAsso).": "The %1 must contain at least 3 characters (HelloAsso rule).",
        "Le %1 ne doit pas contenir 3 caractères répétitifs (règle HelloAsso).": "The %1 must not contain 3 repetitive characters (HelloAsso rule).",
        "Le %1 ne doit pas contenir de chiffres (règle HelloAsso).": "The %1 must not contain numbers (HelloAsso rule).",
        "Le %1 doit contenir au moins une voyelle (règle HelloAsso).": "The %1 must contain at least one vowel (HelloAsso rule).",
        "La valeur du %1 n'est pas autorisée par HelloAsso.": "The value of %1 is not allowed by HelloAsso.",
        "Le %1 contient des caractères non autorisés (règle HelloAsso).": "The %1 contains unauthorized characters (HelloAsso rule).",
        "Le nom et le prénom ne doivent pas être identiques (règle HelloAsso).": "First name and last name must not be identical (HelloAsso rule)."
    }
    
    new_en = {
        "": {
            "msgstr": build_header("en_US"),
            "comments": old_en.get("", {}).get("comments", ""),
        }
    }
        
    for msgid in extracted:
        norm_key = normalize(msgid)
        matched_public_key = None
        for pub_key in public_translations_en:
            if normalize(pub_key) == norm_key:
                matched_public_key = pub_key
                break
                
        if matched_public_key:
            new_en[msgid] = {
                'msgstr': public_translations_en[matched_public_key],
                'comments': "#. Public-facing string in checkout or validation flow"
            }
            
    header_en = old_en.get("", {}).get("comments", "# Translation of HelloAsso Payment Processor in English")
    write_po(new_en, en_po_path, header_en)
    compile_mo(new_en, en_mo_path)
    print(f"Synchronized and compiled en_US.po/mo with public-only translations successfully!")
    
    # Process Spanish (for default spanish fallback of public-facing strings)
    es_po_path = os.path.join(l10n_dir, "es_ES.po")
    es_mo_path = os.path.join(l10n_dir, "es_ES.mo")
    old_es = parse_po(es_po_path)
    
    public_translations_es = {
        "Contribution en ligne": "Contribución en línea",
        "Contribution en ligne : %1": "Contribución en línea: %1",
        "Vous serez redirigé vers HelloAsso pour effectuer le paiement.": "Será redirigido a HelloAsso para realizar el pago.",
        
        # Form validation errors
        "Le nom/prénom doit contenir au moins 3 caractères (règle HelloAsso).": "El nombre/apellido debe contener al menos 3 caracteres (regla HelloAsso).",
        "Le nom/prénom ne doit pas contenir 3 caractères répétitifs (règle HelloAsso).": "El nombre/apellido no debe contener 3 caracteres repetitivos (regla HelloAsso).",
        "Le nom/prénom ne doit pas contenir de chiffres (règle HelloAsso).": "El nombre/apellido no debe contener números (regla HelloAsso).",
        "Le nom/prénom doit contenir au moins une voyelle (règle HelloAsso).": "El nombre/apellido debe contener al menos una vocal (regla HelloAsso).",
        "Cette valeur n'est pas autorisée par HelloAsso.": "Este valor no está permitido por HelloAsso.",
        "Le nom/prénom contient des caractères spéciaux non autorisés (règle HelloAsso).": "El nombre/apellido contiene caracteres especiales no permitidos (regla HelloAsso).",
        "Le nom et le prénom ne doivent pas être identiques (règle HelloAsso).": "El nombre y el apellido no deben ser idénticos (regla HelloAsso).",
        
        # Core API validation errors
        "Le %1 doit contenir au moins 3 caractères (règle HelloAsso).": "El %1 debe contener al menos 3 caracteres (regla HelloAsso).",
        "Le %1 ne doit pas contenir 3 caractères répétitifs (règle HelloAsso).": "El %1 no debe contener 3 caracteres repetitivos (regla HelloAsso).",
        "Le %1 ne doit pas contenir de chiffres (règle HelloAsso).": "El %1 no debe contener números (regla HelloAsso).",
        "Le %1 doit contenir au moins une voyelle (règle HelloAsso).": "El %1 debe contener al menos una vocal (regla HelloAsso).",
        "La valeur du %1 n'est pas autorisée par HelloAsso.": "El valor de %1 no está permitido por HelloAsso.",
        "Le %1 contient des caractères non autorisés (règle HelloAsso).": "El %1 contiene caracteres no permitidos (regla HelloAsso).",
        "Le nom et le prénom ne doivent pas être identiques (règle HelloAsso).": "El nombre y el apellido no deben ser idénticos (regla HelloAsso)."
    }
    
    new_es = {
        "": {
            "msgstr": build_header("es_ES"),
            "comments": old_es.get("", {}).get("comments", ""),
        }
    }
        
    for msgid in extracted:
        norm_key = normalize(msgid)
        matched_public_key = None
        for pub_key in public_translations_es:
            if normalize(pub_key) == norm_key:
                matched_public_key = pub_key
                break
                
        if matched_public_key:
            new_es[msgid] = {
                'msgstr': public_translations_es[matched_public_key],
                'comments': "#. Public-facing string in checkout or validation flow"
            }
            
    header_es = old_es.get("", {}).get("comments", "# Translation of HelloAsso Payment Processor in Spanish")
    write_po(new_es, es_po_path, header_es)
    compile_mo(new_es, es_mo_path)
    print(f"Synchronized and compiled es_ES.po/mo with public-only translations successfully!")
    
    # Generate helloasso-payment-processor.pot template
    pot_path = os.path.join(l10n_dir, "helloasso-payment-processor.pot")
    pot = {
        "": {
            "msgstr": build_header("", is_template=True),
            "comments": "",
        }
    }
    for msgid in extracted:
        pot[msgid] = {'msgstr': "", 'comments': ""}
    write_po(pot, pot_path, "# POT template for HelloAsso Payment Processor")
    print(f"Generated helloasso-payment-processor.pot template successfully!")

if __name__ == "__main__":
    main()
