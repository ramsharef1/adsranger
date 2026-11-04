<?php

namespace App\Connectors;

/** Networks whose Connect arrives in a later wave: only the key test is available. */
class GenericConnector extends Connector
{
    protected const TOKEN_URLS = [
        'snapchat' => 'https://accounts.snapchat.com/login/oauth2/access_token',
        'linkedin' => 'https://www.linkedin.com/oauth/v2/accessToken',
    ];

    public function test(): array
    {
        $key = $this->network->key;
        if (isset(self::TOKEN_URLS[$key])) {
            return $this->oauthClientTest(self::TOKEN_URLS[$key]);
        }
        if ($key === 'microsoft') {
            $tenant = $this->cred('tenant', 'common');

            return $this->oauthClientTest("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", ['scope' => 'https://ads.microsoft.com/msads.manage offline_access']);
        }
        if ($key === 'google_merchant') {
            return (new GoogleAdsConnector($this->network))->test();
        }

        return [null, $this->network->name.' keys can only be checked by connecting an account (comes with its connector).'];
    }
}
