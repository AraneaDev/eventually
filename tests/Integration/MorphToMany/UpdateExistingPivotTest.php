<?php

declare(strict_types=1);

namespace Altek\Eventually\Tests\Integration\MorphToMany;

use Altek\Eventually\Tests\EventuallyTestCase;
use Altek\Eventually\Tests\Models\Award;
use Altek\Eventually\Tests\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

class UpdateExistingPivotTest extends EventuallyTestCase
{
    /**
     * @test
     */
    public function itSuccessfullyRegistersEventListeners(): void
    {
        User::updatingExistingPivot(function ($user, $relation, $properties) {
            $this->assertInstanceOf(User::class, $user);

            $this->assertSame('awards', $relation);

            $this->assertArraySubset([
                [
                    'awardable_id'   => 1,
                    'awardable_type' => User::class,
                    'award_id'       => 1,
                    'prize'          => 4096,
                ],
                [
                    'awardable_id'   => 1,
                    'awardable_type' => User::class,
                    'award_id'       => 2,
                    'prize'          => 4096,
                ],
            ], $properties, true);
        });

        User::existingPivotUpdated(function ($user, $relation, $properties) {
            $this->assertInstanceOf(User::class, $user);

            $this->assertSame('awards', $relation);

            $this->assertArraySubset([
                [
                    'awardable_id'   => 1,
                    'awardable_type' => User::class,
                    'award_id'       => 1,
                    'prize'          => 4096,
                ],
                [
                    'awardable_id'   => 1,
                    'awardable_type' => User::class,
                    'award_id'       => 2,
                    'prize'          => 4096,
                ],
            ], $properties, true);
        });

        $user = factory(User::class)->create();

        $awards = factory(Award::class, 2)->create()->each(function (Award $award) use ($user) {
            $award->users()->attach($user);
        });

        $this->assertCount(2, $user->awards()->get());
        $this->assertSame(2, $user->awards()->updateExistingPivot($awards, [
            'prize' => 4096,
        ]));
    }

    /**
     * @test
     */
    public function itPreventsExistingPivotFromBeingUpdated(): void
    {
        User::updatingExistingPivot(function () {
            return false;
        });

        $user = factory(User::class)->create();

        $awards = factory(Award::class, 2)->create()->each(function (Award $award) use ($user) {
            $award->users()->attach($user);
        });

        $this->assertCount(2, $user->awards()->get());
        $this->assertFalse($user->awards()->updateExistingPivot($awards, [
            'prize' => 256,
        ]));
        $this->assertCount(2, $user->awards()->get());
    }

    /**
     * @test
     * @dataProvider updateExistingPivotProvider
     *
     * @param int   $results
     * @param mixed $id
     * @param array $attributes
     * @param array $expectedPayload
     */
    public function itSuccessfullyUpdatesExistingPivot(int $results, $id, array $attributes, array $expectedPayload): void
    {
        $user = factory(User::class)->create();

        $awards = factory(Award::class, 2)->create()->each(function (Award $award) use ($user) {
            $award->users()->attach($user);
        });

        $this->assertCount(2, $user->awards()->get());

        Event::fake();

        switch ($id) {
            case Model::class:
                $id = $awards->first();
                break;

            case Collection::class:
                $id = $awards;
                break;
        }

        $this->assertSame($results, $user->awards()->updateExistingPivot($id, $attributes));

        Event::assertDispatched(sprintf('eloquent.updatingExistingPivot: %s', User::class), function ($event, $payload, $halt) use ($expectedPayload) {
            $this->assertArraySubset($expectedPayload, $payload, true);

            $this->assertTrue($halt);

            return true;
        });

        Event::assertDispatched(sprintf('eloquent.existingPivotUpdated: %s', User::class), function ($event, $payload) use ($expectedPayload) {
            $this->assertArraySubset($expectedPayload, $payload, true);

            return true;
        });
    }

    /**
     * @return array
     */
    public function updateExistingPivotProvider(): array
    {
        return [
            [
                // Results
                1,

                // Id
                1,

                // Attributes
                [
                    'prize' => 128,
                ],

                // Expected payload
                [
                    1 => 'awards',
                    2 => [
                        [
                            'awardable_id'   => 1,
                            'awardable_type' => User::class,
                            'award_id'       => 1,
                            'prize'          => 128,
                        ],
                    ],
                ],
            ],

            [
                // Results
                1,

                // Id
                [
                    2,
                ],

                // Attributes
                [
                    'prize' => 1024,
                ],

                // Expected payload
                [
                    1 => 'awards',
                    2 => [
                        [
                            'awardable_id'   => 1,
                            'awardable_type' => User::class,
                            'award_id'       => 2,
                            'prize'          => 1024,
                        ],
                    ],
                ],
            ],

            [
                // Results
                2,

                // Id
                [
                    2,
                    1,
                ],

                // Attributes
                [
                    'prize' => 32768,
                ],

                // Expected payload
                [
                    1 => 'awards',
                    2 => [
                        [
                            'awardable_id'   => 1,
                            'awardable_type' => User::class,
                            'award_id'       => 2,
                            'prize'          => 32768,
                        ],
                        [
                            'awardable_id'   => 1,
                            'awardable_type' => User::class,
                            'award_id'       => 1,
                            'prize'          => 32768,
                        ],
                    ],
                ],
            ],

            [
                // Results
                1,

                // Id
                Model::class,

                // Attributes
                [
                    'prize' => 16384,
                ],

                // Expected payload
                [
                    1 => 'awards',
                    2 => [
                        [
                            'awardable_id'   => 1,
                            'awardable_type' => User::class,
                            'award_id'       => 1,
                            'prize'          => 16384,
                        ],
                    ],
                ],
            ],
        ];
    }
}
