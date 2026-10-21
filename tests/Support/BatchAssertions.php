<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

/**
 * For the tests that check hundreds of generated cases: the cases run in one test and the result is one assertion,
 * which lists every case that failed
 */
trait BatchAssertions
{
    /**
     * Assert that every case passed
     * @param string $what What the cases check
     * @param array $results Case name => true when it passed, or the reason why it did not
     * @return void
     */
    private function assertAll(string $what, array $results): void
    {
        $failed = array_filter($results, function ($result) {
            return $result !== true;
        });

        $lines = [];

        foreach (array_slice($failed, 0, 15, true) as $case => $why) {
            $lines[] = "  - $case: $why";
        }

        $this->assertNotEmpty($results, "$what: there were no cases to check");
        $this->assertSame([], array_keys($failed), $what . ': ' . count($failed) . ' of ' . count($results) . " cases failed\n" . implode("\n", $lines));
    }
}
