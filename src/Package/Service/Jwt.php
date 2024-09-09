<?php
/**
 * (c) Remco van der Velde
 */
namespace Package\Raxon\Org\Account\Service;

use DateTimeImmutable;

use Raxon\Org\App;

use Raxon\Org\Module\Data;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;

use Exception;

use Raxon\Org\Exception\AuthorizationException;
use Raxon\Org\Exception\FileWriteException;
use Raxon\Org\Exception\ObjectException;

class Jwt {

    const FIELD = [
        'token.private.key' => 'token.private_key',
        'token.certificate' => 'token.certificate',
        'token.passphrase' => 'token.passphrase',
        'token.issued.by' => 'token.issued_by',
        'token.issued.at' => 'token.issued_at',
        'token.permitted.for' => 'token.permitted_for',
        'token.identified.by' => 'token.identified_by',
        'token.can.only.be.used.after' => 'token.can_only_be_used_after',
        'token.expires.at' => 'token.expires_at',
        'refresh.token.private.key' => 'refresh.token.private_key',
        'refresh.token.certificate' => 'refresh.token.certificate',
        'refresh.token.passphrase' => 'refresh.token.passphrase',
        'refresh.token.issued.by' => 'refresh.token.issued_by',
        'refresh.token.issued.at' => 'refresh.token.issued_at',
        'refresh.token.permitted.for' => 'refresh.token.permitted_for',
        'refresh.token.identified.by' => 'refresh.token.identified_by',
        'refresh.token.can.only.be.used.after' => 'refresh.token.can_only_be_used_after',
        'refresh.token.expires.at' => 'refresh.token.expires_at'
    ];

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function get(App $object, Configuration $configuration, $options=[]): false | Plain
    {
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $config  = $object->parse_read($url, sha1($url));
        if(!$config){
            return false;
        }
        $claim = false;
        if(array_key_exists('user', $options)){
            $user = $options['user'];
            $role = [];
            if(
                property_exists($user, 'role') &&
                is_array($user->role)
            ){
                foreach($user->role as $nr => $user_role){
                    $role[] = [
                        'name' => $user_role->name,
                        'rank' => $user_role->rank,
                        'permission' => [
                            'count' => count($user_role->permission),
                        ]
                    ];
                }
            }
            $claim = (object) [
                'uuid' => $user->uuid,
                'email' => $user->email,
                'role' => $role
            ];
        }
        $now = new DateTimeImmutable();
        return $configuration->builder()
            // Configures the issuer (iss claim)
            ->issuedBy($config->get('token.issued_by'))
            // Configures the audience (aud claim)
            ->permittedFor($config->get('token.permitted_for'))
            // Configures the id (jti claim)
            ->identifiedBy($config->get('token.identified_by'))
            // Configures the time that the token was issue (iat claim)
            ->issuedAt($now->modify($config->get('token.issued_at')))
            // Configures the time that the token can be used (nbf claim)
            ->canOnlyBeUsedAfter($now->modify($config->get('token.can_only_be_used_after')))
            // Configures the expiration time of the token (exp claim)
            ->expiresAt($now->modify($config->get('token.expires_at')))
            // Configures a new claim
            ->withClaim('user', $claim)
            // Builds a new token
            ->getToken($configuration->signer(), $configuration->signingKey());
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     */
    public static function refresh_get(App $object, Configuration $configuration, $options=[]): Plain
    {
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $config  = $object->parse_read($url, sha1($url));
        $claim = false;
        if(
            array_key_exists('user', $options) &&
            property_exists($options['user'], 'uuid') &&
            property_exists($options['user'], 'email')
        ){
            $role = [];
            if(
                property_exists($options['user'], 'role') &&
                is_array($options['user']->role)
            ){
                foreach($options['user']->role as $nr => $user_role){
                    $role[] = [
                        'name' => $user_role->name,
                        'rank' => $user_role->rank,
                        'permission' => [
                            'count' => count($user_role->permission),
                        ]
                    ];
                }
            }
            $claim = (object) [
                'uuid' => $options['user']->uuid,
                'email' => $options['user']->email,
                'role' => $role
            ];
        }
        $now = new DateTimeImmutable();
        return $configuration->builder()
            // Configures the issuer (iss claim)
            ->issuedBy($config->get('refresh.token.issued_by'))
            // Configures the audience (aud claim)
            ->permittedFor($config->get('refresh.token.permitted_for'))
            // Configures the id (jti claim)
            ->identifiedBy($config->get('refresh.token.identified_by'))
            // Configures the time that the token was issue (iat claim)
            ->issuedAt($now->modify($config->get('refresh.token.issued_at')))
            // Configures the time that the token can be used (nbf claim)
            ->canOnlyBeUsedAfter($now->modify($config->get('refresh.token.can_only_be_used_after')))
            // Configures the expiration time of the token (exp claim)
            ->expiresAt($now->modify($config->get('refresh.token.expires_at')))
            // Configures a new header
            ->withClaim('user', $claim)
            // Builds a new token
            ->getToken($configuration->signer(), $configuration->signingKey());
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws \Exception
     */
    public static function configuration(App $object, $options=[]): Configuration
    {
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $config  = $object->parse_read($url, sha1($url));
        $configuration = false;
        if(
            array_key_exists('refresh', $options) &&
            $options['refresh'] === true &&
            $config
        ){
            $configuration = Configuration::forAsymmetricSigner(
            // You may use RSA or ECDSA and all their variations (256, 384, and 512) and EdDSA over Curve25519
                new Signer\Rsa\Sha256(),
                InMemory::file($config->get('refresh.token.private_key')),
                InMemory::file($config->get('refresh.token.certificate'))
            );
        }
        elseif($config) {
            $configuration = Configuration::forAsymmetricSigner(
            // You may use RSA or ECDSA and all their variations (256, 384, and 512) and EdDSA over Curve25519
                new Signer\Rsa\Sha256(),
                InMemory::file($config->get('token.private_key')),
                InMemory::file($config->get('token.certificate'))
            );
        }
        assert($configuration instanceof Configuration);
        return $configuration;
    }

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws FileWriteException
     */
    public static function decryptToken(App $object, $token): UnencryptedToken
    {
        $options = [];
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $config  = $object->parse_read($url, sha1($url));
        $configuration = Jwt::configuration($object, $options);
        assert($configuration instanceof Configuration);
        $token_unencrypted = $configuration->parser()->parse($token);
        assert($token_unencrypted instanceof UnencryptedToken);
        $clock = SystemClock::fromUTC(); // use the clock for issuing and validation
        $configuration->setValidationConstraints(
            new IssuedBy($config->get('token.issued_by')),
            new IdentifiedBy($config->get('token.identified_by')),
            new PermittedFor($config->get('token.permitted_for')),
            new SignedWith(new Sha256(), InMemory::file($config->get('token.certificate'))),
            new StrictValidAt($clock),
            new LooseValidAt($clock)
        );
        $constraints = $configuration->validationConstraints();
        if (!$configuration->validator()->validate($token_unencrypted, ...$constraints)) {
            throw new AuthorizationException('Expired or invalid token...');
        }
        return $token_unencrypted;
    }

    /**
     * @throws AuthorizationException
     * @throws \Raxon\Org\Exception\ObjectException
     * @throws \Raxon\Org\Exception\FileWriteException
     */
    public static function decryptRefreshToken(App $object, $token): UnencryptedToken
    {
        $options = [
            'refresh' => true
        ];
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $config  = $object->parse_read($url, sha1($url));
        $configuration = Jwt::configuration($object, $options);
        assert($configuration instanceof Configuration);
        $token_unencrypted = $configuration->parser()->parse($token);
        assert($token_unencrypted instanceof UnencryptedToken);
        $clock = SystemClock::fromUTC(); // use the clock for issuing and validation
        $configuration->setValidationConstraints(
            new IssuedBy($config->get('refresh.token.issued_by')),
            new IdentifiedBy($config->get('refresh.token.identified_by')),
            new PermittedFor($config->get('refresh.token.permitted_for')),
            new SignedWith(new Sha256(), InMemory::file($config->get('refresh.token.certificate'))),
            new StrictValidAt($clock),
            new LooseValidAt($clock)
        );
        $constraints = $configuration->validationConstraints();
        if (!$configuration->validator()->validate($token_unencrypted, ...$constraints)) {
            throw new AuthorizationException('Authentication failure...');
        }
        return $token_unencrypted;
    }

    /**
     * @throws Exception
     */
    public static function request(App $object): Data
    {
        $request = new Data($object->request());
        $data = new Data();
        foreach(Jwt::FIELD as $key => $attribute){
            if($request->get($key)){
                $data->set($attribute, $request->get($key));
            }
        }
        return $data;
    }
}