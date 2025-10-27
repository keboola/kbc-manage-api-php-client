<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest\Utils;

class EnvVariableHelper
{
    public static function getKbcManageApiUrl(): string
    {
        $variableName = 'KBC_MANAGE_API_URL';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'URL where Keboola Connection is running'
                )
            );
        }
        return $value;
    }

    public static function getKbcManageApiToken(): string
    {
        $variableName = 'KBC_MANAGE_API_TOKEN';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'manage api token assigned to user **with** **superadmin** privileges. Can be created in Account Settings under the title Personal Access Tokens. User must have Multi-Factor Authentication disabled.'
                )
            );
        }
        return $value;
    }

    public static function getKbcSuperApiToken(): string
    {
        $variableName = 'KBC_SUPER_API_TOKEN';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'can be created in manage-apps on the Tokens tab'
                )
            );
        }
        return $value;
    }

    public static function getKbcManageApiSuperTokenWithProjectsReadScope(): string
    {
        $variableName = 'KBC_MANAGE_API_SUPER_TOKEN_WITH_PROJECTS_READ_SCOPE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'can be created in manage-apps on the Tokens tab. Token must have "projects:read" scope'
                )
            );
        }
        return $value;
    }

    public static function getKbcManageApiSuperTokenWithoutScopes(): string
    {
        $variableName = 'KBC_MANAGE_API_SUPER_TOKEN_WITHOUT_SCOPES';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'super token without scopes'
                )
            );
        }
        return $value;
    }

    public static function getKbcManageApiSuperTokenWithDeletedProjectsReadScope(): string
    {
        $variableName = 'KBC_MANAGE_API_SUPER_TOKEN_WITH_DELETED_PROJECTS_READ_SCOPE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'can be created in manage-apps on the Tokens tab. Token must have "deleted-projects:read" scope'
                )
            );
        }
        return $value;
    }

    public static function getKbcManageApiSuperTokenWithUiManageScope(): string
    {
        $variableName = 'KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            $message = sprintf(
                "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                $variableName,
                'can be created in manage-apps on the Tokens tab. Token must have "connection:ui-manage" scope'
            );
            throw new MissingEnvVariableException($message);
        }
        return $value;
    }

    public static function getKbcManageApiSuperTokenWithStorageTokensScope(): string
    {
        $variableName = 'KBC_MANAGE_API_SUPER_TOKEN_WITH_STORAGE_TOKENS_SCOPE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'can be created in manage-apps on the Tokens tab. Token must have "manage:storage-tokens" scope'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestMaintainerId(): string
    {
        $variableName = 'KBC_TEST_MAINTAINER_ID';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'id of maintainer. Please create a new maintainer dedicated to test suite. All maintainer\'s organizations and projects all purged before tests!'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestAdminEmail(): string
    {
        $variableName = 'KBC_TEST_ADMIN_EMAIL';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'email address of another user without any organizations'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestAdminToken(): string
    {
        $variableName = 'KBC_TEST_ADMIN_TOKEN';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'is also a Personal Access Token of user **without** **superadmin** privileges , but for a different user than that which has KBC_MANAGE_API_TOKEN. User must have Multi-Factor Authentication disabled.'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestUnverifiedAdminToken(): string
    {
        $variableName = 'KBC_TEST_UNVERIFIED_ADMIN_TOKEN';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'is a Personal Access Token of an **unverified** user **without** **superadmin** privileges, but for a different user than that which has KBC_MANAGE_API_TOKEN. User must have Multi-Factor Authentication disabled.'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestAdminWithMfaEmail(): string
    {
        $variableName = 'KBC_TEST_ADMIN_WITH_MFA_EMAIL';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'email address of another user without any organizations and having Multi-Factor Authentication enabled'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestAdminWithMfaToken(): string
    {
        $variableName = 'KBC_TEST_ADMIN_WITH_MFA_TOKEN';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'is also a Personal Access Token of user **without** **superadmin** privileges , but for a different user than that which has KBC_MANAGE_API_TOKEN or KBC_TEST_ADMIN_TOKEN'
                )
            );
        }
        return $value;
    }

    // Snowflake
    public static function getKbcTestSnowflakeBackendName(): string
    {
        $variableName = 'KBC_TEST_SNOWFLAKE_BACKEND_NAME';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend username'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestSnowflakeBackendPassword(): string
    {
        $variableName = 'KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend password'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestSnowflakeHost(): string
    {
        $variableName = 'KBC_TEST_SNOWFLAKE_HOST';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake host'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestSnowflakeWarehouse(): string
    {
        $variableName = 'KBC_TEST_SNOWFLAKE_WAREHOUSE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake warehouse'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestSnowflakeBackendRegion(): string
    {
        $variableName = 'KBC_TEST_SNOWFLAKE_BACKEND_REGION';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend region'
                )
            );
        }
        return $value;
    }

    // File Storage - ABS
    public static function getTestAbsAccountKey(): string
    {
        $variableName = 'TEST_ABS_ACCOUNT_KEY';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'First secret key for Azure Storage account'
                )
            );
        }
        return $value;
    }

    public static function getTestAbsRegion(): string
    {
        $variableName = 'TEST_ABS_REGION';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Name of region where Azure Storage Account is located. (Note: AWS region list is used)'
                )
            );
        }
        return $value;
    }

    public static function getTestAbsAccountName(): string
    {
        $variableName = 'TEST_ABS_ACCOUNT_NAME';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Name of Azure Storage account'
                )
            );
        }
        return $value;
    }

    public static function getTestAbsContainerName(): string
    {
        $variableName = 'TEST_ABS_CONTAINER_NAME';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Name of container created inside Azure Storage Account'
                )
            );
        }
        return $value;
    }

    public static function getTestAbsRotateAccountKey(): string
    {
        $variableName = 'TEST_ABS_ROTATE_ACCOUNT_KEY';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Second secret key for Azure Storage account'
                )
            );
        }
        return $value;
    }

    // File Storage - S3
    public static function getTestS3RotateKey(): string
    {
        $variableName = 'TEST_S3_ROTATE_KEY';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Second AWS key'
                )
            );
        }
        return $value;
    }

    public static function getTestS3RotateSecret(): string
    {
        $variableName = 'TEST_S3_ROTATE_SECRET';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Second AWS secret'
                )
            );
        }
        return $value;
    }

    public static function getTestS3FilesBucket(): string
    {
        $variableName = 'TEST_S3_FILES_BUCKET';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Name of file bucket on S3'
                )
            );
        }
        return $value;
    }

    public static function getTestS3Key(): string
    {
        $variableName = 'TEST_S3_KEY';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'First AWS key'
                )
            );
        }
        return $value;
    }

    public static function getTestS3Region(): string
    {
        $variableName = 'TEST_S3_REGION';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Region where your S3 is located'
                )
            );
        }
        return $value;
    }

    public static function getTestS3Secret(): string
    {
        $variableName = 'TEST_S3_SECRET';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'First AWS secret'
                )
            );
        }
        return $value;
    }

    // File Storage - GCS
    public static function getTestGcsKeyfileJson(): string
    {
        $variableName = 'TEST_GCS_KEYFILE_JSON';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'First GCS key file contents as json string'
                )
            );
        }
        return $value;
    }

    public static function getTestGcsKeyfileRotateJson(): string
    {
        $variableName = 'TEST_GCS_KEYFILE_ROTATE_JSON';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Second GCS key file contents as json string used for testing rotation'
                )
            );
        }
        return $value;
    }

    public static function getTestGcsRegion(): string
    {
        $variableName = 'TEST_GCS_REGION';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Region whare GCS is located'
                )
            );
        }
        return $value;
    }

    public static function getTestGcsFilesBucket(): string
    {
        $variableName = 'TEST_GCS_FILES_BUCKET';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Name of file bucket on GCS'
                )
            );
        }
        return $value;
    }

    public static function getKbcManageApiSuperTokenWithOrganizationsReadScope()
    {
        $variableName = 'KBC_MANAGE_API_SUPER_TOKEN_WITH_ORGANIZATIONS_READ_SCOPE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Application token, that has organizations:read scope'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestMainSnowflakeBackendName(): string
    {
        $variableName = 'KBC_TEST_MAIN_SNOWFLAKE_BACKEND_NAME';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend region'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestMainSnowflakeBackendPrivateKey(): string
    {
        $variableName = 'KBC_TEST_MAIN_SNOWFLAKE_BACKEND_PRIVATE_KEY';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend region'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestMainSnowflakeBackendDatabase(): string
    {
        $variableName = 'KBC_TEST_MAIN_SNOWFLAKE_BACKEND_DATABASE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend region'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestMainSnowflakeBackendWarehouse(): string
    {
        $variableName = 'KBC_TEST_MAIN_SNOWFLAKE_BACKEND_WAREHOUSE';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend region'
                )
            );
        }
        return $value;
    }

    public static function getKbcTestSnowflakeBackendClientDbPrefix(): string
    {
        $variableName = 'KBC_TEST_SNOWFLAKE_BACKEND_CLIENT_DB_PREFIX';
        $value = getenv($variableName);
        if ($value === false || $value === '') {
            throw new MissingEnvVariableException(
                sprintf(
                    "Missing required environment variable '%s'. Description: %s\nPlease set it according to the instructions in README.md.",
                    $variableName,
                    'Required for running testCreateStorageBackend: Snowflake backend region'
                )
            );
        }
        return $value;
    }
}
