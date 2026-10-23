<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

use Mariopereira\Fedex\Exception\FedexException;

/**
 * The check that an API sends the scalars of a looser type than the one of the schema (see SchemaValidator::setLooseScalars()),
 * with a request generated from the spec: the first fields of each type that accept them are given a looser value
 */
trait LooseScalars
{
    /**
     * Give looser values to the fields of a generated request, and check that the API sends them as they are
     * @param array $request The request generated from the spec
     * @param array $leaves The leaves of the request (path, type, enum), as the spec helper lists them
     * @param callable $send Sends a request: takes the request and gives the body that was sent (it may throw a FedexException)
     * @return void
     */
    private function assertLooseScalarsAreSent(array $request, array $leaves, callable $send): void
    {
        $loose = ['string' => 12345, 'integer' => '5', 'number' => '5.5', 'boolean' => 'true'];
        $sentAs = [];

        foreach ($leaves as $leaf) {
            $type = $leaf['type'] ?? null;

            if (!isset($loose[$type]) || !empty($leaf['enum']) || isset($sentAs[$type])) {
                continue;
            }

            $copy = SpecInstances::setAt($request, $leaf['path'], $loose[$type]);

            try {
                $body = $send($copy);
            } catch (FedexException $e) {
                continue; // a rule of its own (a pattern, a length) refuses it: try the next field of the type
            }

            if (SpecInstances::getAt($body, $leaf['path']) === $loose[$type]) {
                $sentAs[$type] = implode('.', $leaf['path']);
            }
        }

        $found = [];

        foreach (array_keys($loose) as $type) {
            // the API may not have a field of every type
            if (array_filter($leaves, function ($leaf) use ($type) {
                return ($leaf['type'] ?? null) === $type && empty($leaf['enum']);
            })) {
                $found[$type] = $sentAs[$type] ?? 'no field of this type accepted the looser value';
            }
        }

        $this->assertNotEmpty($sentAs, 'no field accepted a looser type');
        $this->assertNotContains('no field of this type accepted the looser value', $found, json_encode($found));
    }
}
