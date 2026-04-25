POST {{base_url}}/auth/login
Content-Type: application/json
{
  "identifier": "{{login_identifier}}",
  "password": "{{login_password}}"
}
HTTP 200
[Asserts]
jsonpath "$.success" == true
