#!/usr/bin/env bash
set -euo pipefail

BASE_URL="http://127.0.0.1:8080"
COOKIE_FILE="$(mktemp)"
trap 'rm -f "$COOKIE_FILE"' EXIT

json_field() {
  php -r '$j=json_decode(stream_get_contents(STDIN), true); echo $j['"'"'$1'"'"'] ?? "";' 
}

request() {
  curl --fail-with-body --silent --show-error "$@"
}

# Customer signup must work against the real MySQL test schema.
SIGNUP_RESPONSE=$(request -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
  -X POST "$BASE_URL/api/auth.php" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'action=signup' \
  --data-urlencode 'firstName=Test' \
  --data-urlencode 'lastName=Customer' \
  --data-urlencode 'email=ci-customer@example.test' \
  --data-urlencode 'phone=08000000000' \
  --data-urlencode 'address=Test Address' \
  --data-urlencode 'city=Lagos' \
  --data-urlencode 'state=Lagos' \
  --data-urlencode 'password=StrongPass123!')
echo "$SIGNUP_RESPONSE" | grep -q '"success":true'

# Login must establish a PHP session and return the authenticated customer.
LOGIN_RESPONSE=$(request -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
  -X POST "$BASE_URL/api/auth.php" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'action=login' \
  --data-urlencode 'email=ci-customer@example.test' \
  --data-urlencode 'password=StrongPass123!')
echo "$LOGIN_RESPONSE" | grep -q '"success":true'

echo "$LOGIN_RESPONSE" | grep -q '"role":"user"'

# The session cookie must exist after login.
test -s "$COOKIE_FILE"

authenticated_me=$(request -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
  -X POST "$BASE_URL/api/auth.php" \
  --data-urlencode 'action=me')
echo "$authenticated_me" | grep -q '"success":true'
echo "$authenticated_me" | grep -q 'ci-customer@example.test'

# A second session without authentication must be rejected by the protected endpoint.
UNAUTH_COOKIE="$(mktemp)"
trap 'rm -f "$COOKIE_FILE" "$UNAUTH_COOKIE"' EXIT
set +e
unauthenticated=$(curl --silent --show-error -c "$UNAUTH_COOKIE" -b "$UNAUTH_COOKIE" \
  -X POST "$BASE_URL/api/auth.php" --data-urlencode 'action=me')
set -e
echo "$unauthenticated" | grep -q '"success":false'

echo 'Integration smoke tests passed.'
