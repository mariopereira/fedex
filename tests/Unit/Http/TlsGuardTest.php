<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit\Http;

use Mariopereira\Fedex\Misc\FedexUtils;
use PHPUnit\Framework\TestCase;

/**
 * A guard for the rule that TLS verification is never weakened. The package relies on the defaults of cURL, which verify the
 * certificate of the server and its name, and sends everything over https. These tests read the PHP code of src/ (its tokens,
 * so comments and docblocks do not count) and fail if something that could change that appears: an option of cURL that is not
 * one of the six that were reviewed, a stream context, a change of the settings of PHP, an address that is not https. They only
 * read files: no request is made
 */
final class TlsGuardTest extends TestCase
{
    /**
     * The options of cURL that CurlTransport sets, which are the only ones that were reviewed. Adding one, even a safe one,
     * fails the test, so that it is a decision and not an accident
     */
    private const REVIEWED_OPTIONS = ['CURLOPT_CONNECTTIMEOUT', 'CURLOPT_CUSTOMREQUEST', 'CURLOPT_HTTPHEADER', 'CURLOPT_POSTFIELDS', 'CURLOPT_RETURNTRANSFER', 'CURLOPT_TIMEOUT'];

    /**
     * Functions that src/ must not call at all: they set an option of cURL one at a time, or open connections or contexts that
     * would bypass the transport, or change the settings of PHP, or hide the name of a constant
     */
    private const FORBIDDEN_FUNCTIONS = ['curl_setopt', 'stream_context_create', 'stream_context_set_option', 'stream_context_set_params', 'stream_socket_client', 'stream_socket_enable_crypto', 'fsockopen', 'pfsockopen', 'ini_set', 'ini_alter', 'constant'];

    /**
     * The functions of cURL that only CurlTransport may call, and the one that FedexUtils may call
     */
    private const CURL_FUNCTIONS_OF_THE_TRANSPORT = ['curl_init', 'curl_setopt_array', 'curl_exec', 'curl_getinfo', 'curl_error', 'curl_close'];

    /**
     * Parts of a text that no text of src/ may contain: the names of the settings that turn verification off
     */
    private const FORBIDDEN_TEXT_PARTS = ['verify_peer', 'verify_host', 'allow_self_signed', 'peer_fingerprint', 'cafile', 'capath', 'curlopt_', 'ssl_verify', 'sslverify'];

    /**
     * Read the code of a file as the interpreter does, and collect what matters for the guard
     * @param string $code The PHP code
     * @return array 'options' (the names of the constants of cURL that the code uses), 'functions' (the functions of interest that it
     * calls, in lower case) and 'strings' (the texts that it contains, in lower case, without the quotes)
     */
    private static function scan(string $code): array
    {
        $names = [T_STRING];

        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED'] as $name) {
            if (defined($name)) {
                $names[] = constant($name);
            }
        }

        $tokens = token_get_all($code);
        $options = [];
        $functions = [];
        $strings = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], $names, true)) {
                $name = ltrim(substr(strrchr('\\' . $token[1], '\\'), 1));

                if (preg_match('/^(CURLOPT_|CURLSSLOPT_|CURL_SSLVERSION|CURLPROXY_)/', $name)) {
                    $options[$name] = true;
                }

                // a call: the next token that is not a space or a comment is an opening parenthesis
                for ($next = $index + 1; $next < count($tokens); $next++) {
                    if (is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }

                    if ($tokens[$next] === '(' && preg_match('/^(curl_|stream_|openssl_|fsockopen$|pfsockopen$|ini_set$|ini_alter$|constant$)/i', $name)) {
                        $functions[strtolower($name)] = true;
                    }

                    break;
                }
            } elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $strings[] = strtolower(substr($token[1], 1, -1));
            }
        }

        return ['options' => array_keys($options), 'functions' => array_keys($functions), 'strings' => $strings];
    }

    /**
     * What is wrong in the texts of a scan: a setting that turns verification off, a text that is not https
     * @param array $strings The texts of a scan
     * @return string[] A description of each problem
     */
    private static function textProblems(array $strings): array
    {
        $problems = [];

        foreach ($strings as $text) {
            foreach (self::FORBIDDEN_TEXT_PARTS as $part) {
                if (strpos($text, $part) !== false) {
                    $problems[] = "the text '$text' contains '$part'";
                }
            }

            if (in_array($text, ['ssl', 'tls'], true)) {
                $problems[] = "the text '$text' is the key of a context of the streams";
            }

            if (strpos($text, 'http://') === 0) {
                $problems[] = "the text '$text' is not https";
            }
        }

        return $problems;
    }

    /**
     * The files of src/, with their scan
     * @return array Path relative to src/ => the result of scan()
     */
    private static function sources(): array
    {
        $root = realpath(__DIR__ . '/../../../src');
        $out = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (substr((string) $file, -4) === '.php') {
                $out[str_replace('\\', '/', substr((string) $file, strlen($root) + 1))] = self::scan(file_get_contents((string) $file));
            }
        }

        ksort($out);

        return $out;
    }

    // ---------------------------------------------------------------- the code of src/

    /**
     * The guard reads the real sources
     * @return void
     */
    public function testTheGuardReadsTheRealSources(): void
    {
        $sources = self::sources();

        $this->assertGreaterThanOrEqual(20, count($sources));
        $this->assertArrayHasKey('Http/CurlTransport.php', $sources);
        $this->assertSame(self::REVIEWED_OPTIONS, $this->sorted($sources['Http/CurlTransport.php']['options']), 'the scan really finds the options of cURL, so the tests below are not empty');
    }

    /**
     * Only cURL transport sets options of cURL and only the reviewed ones
     * @return void
     */
    public function testOnlyCurlTransportSetsOptionsOfCurlAndOnlyTheReviewedOnes(): void
    {
        $unexpected = [];

        foreach (self::sources() as $file => $found) {
            if ($file === 'Http/CurlTransport.php') {
                $extra = array_values(array_diff($found['options'], self::REVIEWED_OPTIONS));

                if ($extra !== []) {
                    $unexpected[$file] = $extra;
                }
            } elseif ($found['options'] !== []) {
                $unexpected[$file] = $found['options'];
            }
        }

        $this->assertSame([], $unexpected, 'options of cURL that were not reviewed (an option of SSL or TLS would turn verification off)');
    }

    /**
     * CURL transport sets exactly the reviewed options
     * @return void
     */
    public function testCurlTransportSetsExactlyTheReviewedOptions(): void
    {
        $this->assertSame(self::REVIEWED_OPTIONS, $this->sorted(self::sources()['Http/CurlTransport.php']['options']));
    }

    /**
     * No file calls a function that could bypass the transport or change the TLS settings
     * @return void
     */
    public function testNoFileCallsAFunctionThatCouldBypassTheTransportOrChangeTheTlsSettings(): void
    {
        $found = [];

        foreach (self::sources() as $file => $scan) {
            $bad = array_values(array_intersect($scan['functions'], self::FORBIDDEN_FUNCTIONS));

            foreach ($scan['functions'] as $function) {
                if (strpos($function, 'openssl_') === 0) {
                    $bad[] = $function;
                }
            }

            if ($bad !== []) {
                $found[$file] = $bad;
            }
        }

        $this->assertSame([], $found);
    }

    /**
     * The functions of cURL are called only by the transport and fedex utils may close
     * @return void
     */
    public function testTheFunctionsOfCurlAreCalledOnlyByTheTransportAndFedexUtilsMayClose(): void
    {
        $found = [];

        foreach (self::sources() as $file => $scan) {
            $curl = array_values(array_filter($scan['functions'], function ($function) {
                return strpos($function, 'curl_') === 0 && $function !== 'curl_setopt';
            }));
            $allowed = ($file === 'Http/CurlTransport.php') ? self::CURL_FUNCTIONS_OF_THE_TRANSPORT : (($file === 'Misc/FedexUtils.php') ? ['curl_close'] : []);
            $extra = array_values(array_diff($curl, $allowed));

            if ($extra !== []) {
                $found[$file] = $extra;
            }
        }

        $this->assertSame([], $found);
    }

    /**
     * No text of the code names a setting that turns verification off or is not https
     * @return void
     */
    public function testNoTextOfTheCodeNamesASettingThatTurnsVerificationOffOrIsNotHttps(): void
    {
        $found = [];

        foreach (self::sources() as $file => $scan) {
            $problems = self::textProblems($scan['strings']);

            if ($problems !== []) {
                $found[$file] = $problems;
            }
        }

        $this->assertSame([], $found);
    }

    /**
     * The base URLs are https
     * @return void
     */
    public function testTheBaseUrlsAreHttps(): void
    {
        $this->assertStringStartsWith('https://', FedexUtils::getUrl(true));
        $this->assertStringStartsWith('https://', FedexUtils::getUrl(false));
        $this->assertStringStartsWith('https://', FedexUtils::SANDBOX_URL);
        $this->assertStringStartsWith('https://', FedexUtils::PRODUCTION_URL);
    }

    // ---------------------------------------------------------------- the guard itself

    /**
     * @dataProvider snippetsProvider
     * @param string $code A piece of code
     * @param array $options The options of cURL that the scan has to find
     * @param array $functions The functions of interest that the scan has to find
     * @param array $problems The problems that the texts have to give
     */
    public function testTheScannerSeesWhatItHasToSee(string $code, array $options, array $functions, array $problems): void
    {
        $scan = self::scan('<?php ' . $code);

        $this->assertSame($options, $this->sorted($scan['options']));
        $this->assertSame($functions, $this->sorted($scan['functions']));
        $this->assertSame($problems, self::textProblems($scan['strings']));
    }

    /**
     * The cases of testTheScannerSeesWhatItHasToSee(): the scanner sees what it has to see
     * @return array
     */
    public function snippetsProvider(): array
    {
        return [
            'the verification of the peer turned off' => ['curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);', ['CURLOPT_SSL_VERIFYPEER'], ['curl_setopt'], []],
            'the verification of the host turned off in an array' => ['curl_setopt_array($c, [CURLOPT_SSL_VERIFYHOST => 0]);', ['CURLOPT_SSL_VERIFYHOST'], ['curl_setopt_array'], []],
            'a fully qualified constant' => ['$o = [\CURLOPT_SSL_VERIFYPEER => false];', ['CURLOPT_SSL_VERIFYPEER'], [], []],
            'a fully qualified function' => ['\curl_setopt($c, 64, false);', [], ['curl_setopt'], []],
            'an option of the ssl settings' => ['$o = [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NO_REVOKE];', ['CURLOPT_SSL_OPTIONS', 'CURLSSLOPT_NO_REVOKE'], [], []],
            'a version of ssl' => ['$o = [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_0];', ['CURLOPT_SSLVERSION', 'CURL_SSLVERSION_TLSv1_0'], [], []],
            'a stream context with the verification off' => ["stream_context_create(['ssl' => ['verify_peer' => false]]);", [], ['stream_context_create'], ["the text 'ssl' is the key of a context of the streams", "the text 'verify_peer' contains 'verify_peer'"]],
            'a name of a setting in a text' => ["\$name = 'CURLOPT_SSL_VERIFYPEER';", [], [], ["the text 'curlopt_ssl_verifypeer' contains 'curlopt_'", "the text 'curlopt_ssl_verifypeer' contains 'ssl_verify'"]],
            'a constant looked up by its name' => ["constant('X');", [], ['constant'], []],
            'a change of the settings of php' => ["ini_set('curl.cainfo', '');", [], ['ini_set'], []],
            'a certificate file' => ["\$o = ['cafile' => '/tmp/x'];", [], [], ["the text 'cafile' contains 'cafile'"]],
            'a url that is not https' => ["\$u = 'http://example.com/';", [], [], ["the text 'http://example.com/' is not https"]],
            'a url that is https' => ["\$u = 'https://example.com/';", [], [], []],
            'a function of openssl' => ['openssl_x509_parse($x);', [], ['openssl_x509_parse'], []],
            'a space before the parenthesis' => ['curl_setopt ($c, 1, 2);', [], ['curl_setopt'], []],
            'a comment between the name and the parenthesis' => ['curl_setopt /* x */ ($c, 1, 2);', [], ['curl_setopt'], []],
            'only a line comment' => ['// curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false); http://x', [], [], []],
            'only a block comment' => ['/* CURLOPT_SSL_VERIFYPEER ini_set stream_context_create */', [], [], []],
            'only a docblock' => ["/**\n * CURLOPT_SSL_VERIFYPEER 'verify_peer' http://x\n */", [], [], []],
            'a name that only looks like a function' => ['$x = $this->ini_set;', [], [], []],
            'safe code' => ['$c = curl_init($u); curl_exec($c);', [], ['curl_exec', 'curl_init'], []],
        ];
    }

    /**
     * Sort a list of names
     * @param array $names The names
     * @return array
     */
    private function sorted(array $names): array
    {
        sort($names);

        return array_values($names);
    }
}
