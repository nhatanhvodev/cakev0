# GetTemplateRequest


## Fields

| Field                                                                                | Type                                                                                 | Required                                                                             | Description                                                                          |
| ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ |
| `templateType`                                                                       | [Operations\PathParamTemplateType](../../Models/Operations/PathParamTemplateType.md) | :heavy_check_mark:                                                                   | The type of templates to retrieve (email or SMS)                                     |
| `slug`                                                                               | *string*                                                                             | :heavy_check_mark:                                                                   | The slug (i.e. machine-friendly name) of the template to retrieve                    |