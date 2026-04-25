
POST {{base_url}}/auth/verify-otp
Content-Type: application/json
{
  "identifier": "{{login_identifier}}",
  "otp": "{{otp_code}}"
}
HTTP 200
[Asserts]
jsonpath "$.success" == true
[Captures]
access_token: jsonpath "$.data.access_token"
