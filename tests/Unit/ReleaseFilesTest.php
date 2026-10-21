<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests of what goes into the archive that Composer downloads. The archive is made from the files of the repository
 * that .gitattributes does not mark with export-ignore, so these tests read .gitattributes and the files of the
 * repository: nothing else may be added to a release unnoticed. They cannot see how Git itself matches a pattern
 */
final class ReleaseFilesTest extends TestCase
{
    /**
     * What a release contains: the top-level files and directories that are not export-ignored
     * @var string[]
     */
    private const SHIPPED = ['LICENSE', 'README.md', 'composer.json', 'src'];

    /**
     * The root of the repository
     * @return string
     */
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * The lines of a file that are not blank or comments
     * @param string $name The file, in the root of the repository
     * @return string[]
     */
    private static function rules(string $name): array
    {
        $lines = preg_split('/\r?\n/', file_get_contents(self::root() . '/' . $name));

        return array_values(array_filter(array_map('trim', $lines), function (string $line) {
            return ($line !== '') && ($line[0] !== '#');
        }));
    }

    /**
     * The top-level entries that .gitattributes marks with export-ignore
     * @return string[]
     */
    private static function exportIgnored(): array
    {
        $ignored = [];

        foreach (self::rules('.gitattributes') as $rule) {
            if (preg_match('#^/([^\s/]+)/?\s+export-ignore$#', $rule, $match)) {
                $ignored[] = $match[1];
            }
        }

        return $ignored;
    }

    /**
     * The top-level entries that Git does not track, because .gitignore names them (the ones that only exist on a
     * machine that built or ran something)
     * @return string[]
     */
    private static function untracked(): array
    {
        $untracked = ['.git'];

        foreach (self::rules('.gitignore') as $rule) {
            if (preg_match('#^/([^/]+)/?$#', $rule, $match)) {
                $untracked[] = $match[1];
            }
        }

        return $untracked;
    }

    /**
     * The top-level entries of the repository that a release would contain
     * @return string[] Sorted
     */
    private static function shipped(): array
    {
        $entries = array_diff(scandir(self::root()), ['.', '..'], self::untracked(), self::exportIgnored());
        sort($entries);

        return array_values($entries);
    }

    /**
     * Every rule of .gitattributes is the name of a top-level entry with export-ignore
     * @return void
     */
    public function testEveryRuleOfGitattributesIsTheNameOfATopLevelEntryWithExportIgnore(): void
    {
        $rules = self::rules('.gitattributes');

        $this->assertNotEmpty($rules);

        foreach ($rules as $rule) {
            $this->assertMatchesRegularExpression('#^/[^\s/]+/?\s+export-ignore$#', $rule, "the rule '$rule' is not of the form '/name export-ignore'");
        }

        $this->assertSame(array_values(array_unique($rules)), $rules, 'a rule is repeated');
    }

    /**
     * A release contains exactly the license, the README, composer.json and the source
     * @return void
     */
    public function testAReleaseContainsExactlyTheLicenseTheReadmeComposerJsonAndTheSource(): void
    {
        $expected = self::SHIPPED;
        sort($expected);

        $this->assertSame($expected, self::shipped(), 'a top-level file or directory would go into every release: mark it with export-ignore in .gitattributes, or add it to SHIPPED on purpose');
    }

    /**
     * What is only for development is left out
     * @return void
     */
    public function testWhatIsOnlyForDevelopmentIsLeftOut(): void
    {
        $ignored = self::exportIgnored();

        foreach (['tests', 'docs', 'schemas', 'phpunit.xml.dist', 'composer.lock', 'CONTRIBUTING.md', '.claude', '.gitattributes', '.gitignore', '.phpdoc'] as $name) {
            $this->assertContains($name, $ignored, "$name has to be left out of a release");
        }
    }

    /**
     * Everything that the autoload of composer.json needs is in the release
     * @return void
     */
    public function testEverythingThatTheAutoloadOfComposerJsonNeedsIsInTheRelease(): void
    {
        $composer = json_decode(file_get_contents(self::root() . '/composer.json'), true);
        $shipped = self::shipped();
        $paths = [];

        foreach ($composer['autoload']['psr-4'] ?? [] as $path) {
            $paths = array_merge($paths, (array) $path);
        }

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $top = explode('/', trim($path, '/'))[0];

            $this->assertContains($top, $shipped, "the autoload needs $path, which is left out of a release");
            $this->assertDirectoryExists(self::root() . '/' . $top);
        }

        foreach ($composer['autoload']['files'] ?? [] as $file) {
            $this->assertContains(explode('/', $file)[0], $shipped, "the autoload needs $file, which is left out of a release");
        }
    }

    /**
     * Nothing that the code reads at run time is left out of a release
     * @return void
     */
    public function testNothingThatTheCodeReadsAtRunTimeIsLeftOutOfARelease(): void
    {
        $ignored = self::exportIgnored();
        $src = self::root() . '/src';
        $reads = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            // Only the strings of the code count: the docblocks name the schemas files, which is not reading them
            foreach (token_get_all(file_get_contents((string) $file)) as $token) {
                if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) && preg_match('#(?:schemas|docs|tests)/#', $token[1])) {
                    $reads[] = substr((string) $file, strlen($src) + 1) . ': ' . $token[1];
                }
            }
        }

        $this->assertSame([], $reads, 'the code reads a file of a directory that is left out of a release');
        $this->assertContains('schemas', $ignored);
    }
}
