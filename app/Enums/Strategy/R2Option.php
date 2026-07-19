<?php

namespace App\Enums\Strategy;

final class R2Option
{
    /** @var string 访问url */
    const Url = 'url';

    /** @var string AccessKeyId */
    const AccessKeyId = 'access_key_id';

    /** @var string SecretAccessKey */
    const SecretAccessKey = 'secret_access_key';

    /** @var string S3 API Endpoint */
    const Endpoint = 'endpoint';

    /** @var string Bucket */
    const Bucket = 'bucket';
}
