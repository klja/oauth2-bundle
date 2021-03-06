<?php

namespace Trikoder\Bundle\OAuth2Bundle\Model;

use RuntimeException;
use Trikoder\Bundle\OAuth2Bundle\Oauth2Grants;

class Grant
{
    /**
     * @var string
     */
    private $grant;

    public function __construct(string $grant)
    {
        if (!Oauth2Grants::has($grant)) {
            throw new RuntimeException(
                sprintf('The \'%s\' grant is not supported.', $grant)
            );
        }

        $this->grant = $grant;
    }

    public function __toString(): string
    {
        return $this->grant;
    }
}
