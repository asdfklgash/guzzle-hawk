<?php

namespace ComplexMedia\Guzzle\Plugin;


use Dflydev\Hawk\Client\ClientBuilder;
use Dflydev\Hawk\Credentials\Credentials;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\EmitterInterface;
use GuzzleHttp\Event\SubscriberInterface;

/**
 *  A Hawk signer for Guzzle.
 *
 *  @author Jon Eskew <jonathan@jeskew.net>
 *
 *  Currently maintained by Complex Media.
 */
class Hawk implements SubscriberInterface
{

    /**
     *  The Hawk key - the public identifier of the pair.
     */
    private $key;

    /**
     *  The Hawk secret - The private HMAC key.
     */
    private $secret;

    /**
     *  Offset
     */
    private $offset;

    /**
     *  Which signing Algorithm to use.
     */
    private $algorithm;

    /**
     *  The Dflydev\Hawk client.
     */
    private $client;

    /**
     *  Dflydev\Hawk\Credentials\Credentials
     */
    private $credentials;

    /**
     *  A list of readable properties
     */
    private static $readable = ['key', 'secret', 'offset', 'algorithm'];

    /**
     *  Encapsulated logic to see if a property is readable.
     *
     *  @param string $name The property desired.
     *
     *  @return boolean True if the property is in $readable.  False otherwise.
     */
    public static function isReadable($name)
    {
        return in_array($name, Hawk::$readable);
    }

    /**
     *  Constructor
     *
     *  @param string $key The public identifier
     *  @param string $secret The HMAC Key.
     *  @param string $algorithm The HMAC Algorithm.  Defaults to 'sha256' for historical reasons.
     *  @param int $offset Time skew in seconds.
     */
    public function __construct($key, $secret, $algorithm = 'sha256', $offset = 0)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->offset = $offset;
        $this->algorithm = $algorithm;
        $this->client = $this->buildClient();
        $this->credentials = $this->generateCredentials();
    }

    /**
     *  Magic Getter to read the key, secret, offset, or algorithm.
     *
     *  @param string $name The name of the parameter desired.
     *
     *  @return mixed
     */
    public function __get($name)
    {
        if (Hawk::isReadable($name))
            return $this->{$name};
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
        );
        return null;
    }

    /**
     *  Returns isset() for readable properties.
     *
     *  @param string $name the name of the property in question
     *
     *  @return boolean
     */
    public function __isset($name)
    {
        if (Hawk::isReadable($name))
            return isset($this->{$name});
        return false;
    }

    /**
     *  Set the readable properties, updating the non-readable properties as are necessary.
     *
     *  @param string $name the property in question.
     *  @param mixed $value the new value.
     *
     *  @return void
     */
    public function __set($name, $value)
    {
        if (Hawk::isReadable($name)) {
            $trace = debug_backtrace();
            trigger_error(
                'Cannot write to property via __set(): ' . $name .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE
            );
            return;
        }
        $this->{$name} = $value;
        if (in_array($name, ['key', 'secret', 'algorithm']))
            $this->credentials = $this->generateCredentials();
        if ($name === 'offset')
            $this->cilent = $this->buildClient();
    }

    /**
     *  Inherited from SubscriberInterface
     *
     *  @return Array
     */
    public function getEvents()
    {
        return [
            'before' => ['signRequest', 'last'],
        ];
    }

    /**
     *  Sign a request - a Listener for SubscriberInterface
     *
     *  @param GuzzleHttp\Event\BeforeEvent $event
     *
     *  @return void
     */
    public function signRequest(BeforeEvent $event)
    {
        $request = $event->getRequest();

        $hawkRequest = $this->generateHawkRequest(
            $request->getUrl(),
            $request->getMethod()
        );

        $request->setHeader(
            $hawkRequest->header()->fieldName(),
            $hawkRequest->header()->fieldValue()
        );
    }

    /**
     *  Generate a Hawk Request Signature.
     *
     *  @param string $url The URL being requested
     *  @param string $method The HTTP Method being used (GET, PUT, POST, etc)
     *  @param array $ext Any application specific extra data to pass along.
     *  @param string $payload The payload body.
     *  @param string $contentType ContentType Header
     *
     *  @return Hawk Data for the request.
     */
    public function generateHawkRequest(
        $url,
        $method = 'GET',
        $ext = [],
        $payload = '',
        $contentType = ''
    ) {
        $requestOptions = $this->generateRequestOptions($ext, $payload, $contentType);

        $request = $this->client->createRequest(
            $this->credentials,
            $url,
            $method,
            $requestOptions
        );

        return $request;
    }

    /**
     *  Build a client for request Signing.
     *
     *  @return Dflydev\Hawk\Client\Client a Client for signing.
     */
    private function buildClient()
    {
        $builder =  ClientBuilder::create();
        if ($this->offset) {
            $builder = $builder->setLocaltimeOffset($this->offset);
        }
        return $builder->build();
    }

    /**
     *  Generate a Credentials object for request Signing.
     *
     *  @return Dflydev\Hawk\Credentials\Credentials a Hawk Credentials Object.
     */
    private function generateCredentials()
    {
        return new Credentials($this->secret, $this->algorithm, $this->key);
    }

    /**
     *  Generate an Array with the correct Request options.
     *
     *  @param array $ext Application specific EXT data, if there is any.
     *  @param string $payload The request payload.
     *  @param string $contentType The content Type of the request.
     *
     *  @return array the Request Options.
     */
    private function generateRequestOptions($ext, $payload, $contentType)
    {
        $requestOptions = [];
        if ($payload && $contentType) {
            $requestOptions['payload'] = $payload;
            $requestOptions['content_type'] = $contentType;
        }

        if ($ext) {
            $requestOptions['ext'] = http_build_query($ext);
        }

        return $requestOptions;
    }
}
