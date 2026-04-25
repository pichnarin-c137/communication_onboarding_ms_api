GET {{base_url}}/healthcheck
HTTP 200
[Asserts]
jsonpath "$.status" == "OK"
