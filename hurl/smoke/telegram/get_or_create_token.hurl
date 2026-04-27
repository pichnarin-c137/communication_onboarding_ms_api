GET {{base_url}}/telegram/setup-token?client_id=dddddddd-0000-0000-0000-000000000002
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
jsonpath "$.success" == true
