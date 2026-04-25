GET {{base_url}}/onboarding/{{onboarding_id}}/appointments
Authorization: Bearer {{access_token}}
HTTP 200
[Asserts]
jsonpath "$.success" == true
