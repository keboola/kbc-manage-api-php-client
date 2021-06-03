<?php

$openApi = file_get_contents('./openapi.yml');
$openApi = str_replace([
    '#/components/schemasMaintainerModel',
    '#/components/schemasOrganizationModel',
    '#/components/schemasProjectModel',
    'components:',
    '  - url: https://connection.keboola.com/'
], [
    '#/components/schemas/MaintainerModel',
    '#/components/schemas/OrganizationModel',
    '#/components/schemas/ProjectModel',
    "
security:
  - ApiKeyAuth: []
components:
  securitySchemes:
    ApiKeyAuth:
      type: apiKey
      in: header     
      name: X-KBC-ManageApiToken",
    '  - url: https://connection.keboola.com/
  - url: https://connection.eu-central-1.keboola.com/
  - url: https://connection.north-europe.azure.keboola.com/'

], $openApi);

file_put_contents('./openapi.yml', $openApi);
