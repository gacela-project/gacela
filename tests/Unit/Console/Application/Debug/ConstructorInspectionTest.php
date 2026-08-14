<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Debug;

use Gacela\Console\Application\Debug\ConstructorInspection;
use Gacela\Console\Application\Debug\ParameterInspection;
use Gacela\Console\Application\Debug\ParameterStatus;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * The counts `debug:modules --check` acts on.
 *
 * Asserted here rather than through the command, which only observes whether a
 * total is zero: a count that walked the wrong way or started at the wrong
 * number still produces a non-zero total and the same exit code, so the
 * command's own tests cannot see the difference.
 */
final class ConstructorInspectionTest extends TestCase
{
    /**
     * `unresolvable` is the union of the two: what the container cannot supply,
     * plus what nothing here looked at.
     */
    public function test_it_separates_a_fault_from_a_parameter_it_declined_to_inspect(): void
    {
        $inspection = $this->inspectionOf(
            ParameterStatus::Bound,
            ParameterStatus::Autowirable,
            ParameterStatus::UnboundInterface,
            ParameterStatus::ScalarWithoutDefault,
            ParameterStatus::UnsupportedType,
        );

        self::assertSame(2, $inspection->resolvableCount());
        self::assertSame(3, $inspection->unresolvableCount());
        self::assertSame(2, $inspection->faultCount());
        self::assertSame(1, $inspection->notInspectedCount());
    }

    /**
     * A type that does not exist is a typo or a deleted class, not a shape the
     * inspector declined to read -- so it fails a check, and a union type does
     * not.
     */
    public function test_a_missing_type_is_a_fault_and_a_union_type_is_not(): void
    {
        self::assertSame(1, $this->inspectionOf(ParameterStatus::MissingType)->faultCount());
        self::assertSame(0, $this->inspectionOf(ParameterStatus::MissingType)->notInspectedCount());

        self::assertSame(0, $this->inspectionOf(ParameterStatus::UnsupportedType)->faultCount());
        self::assertSame(1, $this->inspectionOf(ParameterStatus::UnsupportedType)->notInspectedCount());
    }

    public function test_a_constructor_with_nothing_wrong_counts_none_of_either(): void
    {
        $inspection = $this->inspectionOf(ParameterStatus::Bound, ParameterStatus::HasDefault, ParameterStatus::Inject);

        self::assertSame(0, $inspection->faultCount());
        self::assertSame(0, $inspection->notInspectedCount());
        self::assertTrue($inspection->isFullyResolvable());
    }

    public function test_a_constructor_with_no_parameters_counts_none_of_either(): void
    {
        $inspection = $this->inspectionOf();

        self::assertSame(0, $inspection->faultCount());
        self::assertSame(0, $inspection->notInspectedCount());
    }

    /**
     * Every status lands on exactly one side, so a new one cannot be silently
     * neither -- which would make it invisible to both the report and the check.
     */
    public function test_every_status_is_resolvable_a_fault_or_not_inspected(): void
    {
        foreach (ParameterStatus::cases() as $status) {
            $sides = (int)$status->isResolvable() + (int)$status->isFault() + (int)$status->isNotInspected();

            self::assertSame(1, $sides, sprintf('%s lands on %d sides, not exactly one', $status->value, $sides));
        }
    }

    private function inspectionOf(ParameterStatus ...$statuses): ConstructorInspection
    {
        $parameters = [];
        foreach ($statuses as $i => $status) {
            $parameters[] = new ParameterInspection('$p' . $i, 'mixed', $status, 'detail');
        }

        return new ConstructorInspection(self::class, $parameters !== [], $parameters);
    }
}
