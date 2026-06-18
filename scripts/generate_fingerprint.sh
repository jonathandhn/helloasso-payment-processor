#!/usr/bin/env bash

# This script generates a SHA-256 fingerprint for a HelloAsso OAuth key pair (Client ID + Client Secret)
# to be used in CRM/HelloassoPaymentProcessor/PartnerCredentials.php for key rotation.

set -e

# Help function
show_help() {
  echo "Usage: $0 [client_id] [client_secret]"
  echo "Or run without arguments for interactive prompts."
  exit 0
}

if [ "$1" == "-h" ] || [ "$1" == "--help" ]; then
  show_help
fi

CLIENT_ID="$1"
CLIENT_SECRET="$2"

# Interactive prompt if arguments are missing
if [ -z "$CLIENT_ID" ]; then
  read -r -p "Enter HelloAsso Client ID: " CLIENT_ID
fi

if [ -z "$CLIENT_SECRET" ]; then
  read -r -s -p "Enter HelloAsso Client Secret: " CLIENT_SECRET
  echo "" # New line after hidden input
fi

# Clean whitespace
CLIENT_ID=$(echo "$CLIENT_ID" | tr -d '[:space:]')
CLIENT_SECRET=$(echo "$CLIENT_SECRET" | tr -d '[:space:]')

if [ -z "$CLIENT_ID" ] || [ -z "$CLIENT_SECRET" ]; then
  echo "Error: Both Client ID and Client Secret are required." >&2
  exit 1
fi

# Generate SHA-256 fingerprint exactly as PHP's hash('sha256', clientId . "\n" . clientSecret)
# Note: we use printf to avoid adding trailing newlines from echo, and we use a literal '\n'
# matching the exact PHP concatenation schema.
FINGERPRINT=$(printf "%s\n%s" "$CLIENT_ID" "$CLIENT_SECRET" | shasum -a 256 | awk '{print $1}')

echo "========================================================="
echo "HelloAsso Key Fingerprint Calculator"
echo "========================================================="
echo "Client ID:  $CLIENT_ID"
echo "Fingerprint (SHA-256):"
echo "  $FINGERPRINT"
echo "========================================================="
echo "Add this fingerprint to the COMMUNITY_FINGERPRINTS array in:"
echo "  CRM/HelloassoPaymentProcessor/PartnerCredentials.php"
echo "========================================================="
