GET {{base_url}}/get-profile
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
jsonpath "$.success" == true
