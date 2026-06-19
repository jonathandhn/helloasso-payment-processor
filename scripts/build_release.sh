#!/usr/bin/env bash

set -euo pipefail

VERSION="${1:-}"
if [[ -z "${VERSION}" ]]; then
  echo "Usage: scripts/build_release.sh <version>" >&2
  exit 2
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
PACKAGE_NAME="helloasso-payment-processor"
PACKAGE_DIR="${BUILD_DIR}/${PACKAGE_NAME}"
ARCHIVE="${ROOT_DIR}/${PACKAGE_NAME}-${VERSION}.zip"
CREDENTIAL_FILE="${PACKAGE_DIR}/CRM/HelloassoPaymentProcessor/PartnerCredentials.php"

rm -rf "${BUILD_DIR}"
rm -f "${ARCHIVE}"
mkdir -p "${PACKAGE_DIR}"

git -C "${ROOT_DIR}" archive --format=tar HEAD | tar -xf - -C "${PACKAGE_DIR}"
php "${PACKAGE_DIR}/scripts/inject_release_credentials.php" "${CREDENTIAL_FILE}"

find "${PACKAGE_DIR}" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

if grep -q '%%HELLOASSO_' "${CREDENTIAL_FILE}"; then
  echo "Credential placeholders remain in the packaged extension." >&2
  exit 1
fi

(
  cd "${BUILD_DIR}"
  zip -qr "${ARCHIVE}" "${PACKAGE_NAME}"
)

unzip -tq "${ARCHIVE}"
ARCHIVE_LIST="${BUILD_DIR}/archive-files.txt"
ARCHIVE_CREDENTIAL_FILE="${BUILD_DIR}/archive-PartnerCredentials.php"
unzip -Z1 "${ARCHIVE}" > "${ARCHIVE_LIST}"

for required_file in \
  "${PACKAGE_NAME}/info.xml" \
  "${PACKAGE_NAME}/helloasso_payment_processor.php" \
  "${PACKAGE_NAME}/CRM/HelloassoPaymentProcessor/PartnerCredentials.php"
do
  if ! grep -Fxq "${required_file}" "${ARCHIVE_LIST}"; then
    echo "Release archive is missing ${required_file}." >&2
    exit 1
  fi
done

unzip -p "${ARCHIVE}" \
  "${PACKAGE_NAME}/CRM/HelloassoPaymentProcessor/PartnerCredentials.php" \
  > "${ARCHIVE_CREDENTIAL_FILE}"
if grep -q '%%HELLOASSO_' "${ARCHIVE_CREDENTIAL_FILE}"; then
  echo "Credential placeholders remain inside the release archive." >&2
  exit 1
fi

echo "Release archive validated: ${ARCHIVE}"
