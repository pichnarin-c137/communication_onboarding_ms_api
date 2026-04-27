GET {{base_url}}/telegram/groups
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
jsonpath "$.success" == true
