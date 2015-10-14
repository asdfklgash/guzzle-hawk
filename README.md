A Hawk Authenticator for Guzzle.  

Originally developed by [jeskew](https://github.com/jeskew/guzzle-hawk), this
extremely simple Plugin provides middleware you can place on your Guzzle client to automagically
sign your requests.

## Usage

First, install with Composer:

    composer require complexmedia/guzzle-hawk

Then, use it in code.

    use ComplexMedia\Guzzle\Plugin\Hawk;
    use GuzzleHttp\Client as Guzzle;

    $client = new Guzzle();
    $signer = new Hawk($key, $secret, $algorithm, $offset);

    $client->getEmitter()->attach($signer);
    $response = $client->get($URL);

`$algorithm` will default to `'sha256'`, but you can set it to whatever your system supports.
`$offset` adds a clock skew to synchronize with your server, and defaults to `0`.  If you
get a 401 from your server, you can adjust several properties on the signer and try again 
immediately:

    $signer->key = $new_key
    $signer->secret = $new_secret
    $signer->algorithm = $new_algorithm
    $signer->offset = $new_offset

    $response = $client->get($URL);

## Contributing

Yes

## License

MIT License applies.