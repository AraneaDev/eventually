<?php

declare(strict_types=1);

namespace Altek\Eventually\Tests\Integration\MorphToMany;

use Altek\Eventually\Tests\EventuallyTestCase;
use Altek\Eventually\Tests\Models\Award;
use Altek\Eventually\Tests\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Event;

class AttachTest extends EventuallyTestCase
{
    /**
     * @test
     */
    public function itSuccessfullyRegistersEventListeners(): void
    {
        User::attaching(function ($user, $relation, $data) {
            $this->assertInstanceOf(User::class, $user);

            $this->assertSame('awards', $relation);

            $this->assertArraySubset([
                1 => [],
                2 => [],
            ], $data, true);
        });

        User::attached(function ($user, $relation, $data) {
            $this->assertInstanceOf(User::class, $user);

            $this->assertSame('awards', $relation);

            $this->assertArraySubset([
                1 => [],
                2 => [],
            ], $data, true);
        });

        $user = factory(User::class)->create();
        $awards = factory(Award::class, 2)->create();

        $this->assertCount(0, $user->awards()->get());
        $this->assertTrue($user->awards()->attach($awards));
        $this->assertCount(2, $user->awards()->get());
    }

    /**
     * @test
     */
    public function itPreventsModelsFromBeingAttached(): void
    {
        User::attaching(function () {
            return false;
        });

        $user = factory(User::class)->create();
        $awards = factory(Award::class, 2)->create();

        $this->assertCount(0, $user->awards()->get());
        $this->assertFalse($user->awards()->attach($awards));
        $this->assertCount(0, $user->awards()->get());
    }

    /**
     * @test
     * @dataProvider attachProvider
     *
     * @param mixed $id
     * @param array $attributes
     * @param array $expectedPayload
     */
    public function itSuccessfullyAttachesModels($id, array $attributes, array $expectedPayload): void
    {
        $user = factory(User::class)->create();
        $awards = factory(Award::class, 2)->create();

        $this->assertCount(0, $user->awards()->get());

        Event::fake();

        switch ($id) {
            case Model::class:
                $id = $awards->first();
                break;

            case Collection::class:
                $id = $awards;
                break;
        }

        $this->assertTrue($user->awards()->attach($id, $attributes));

        Event::assertDispatched(sprintf('eloquent.attaching: %s', User::class), function ($event, $payload, $halt) use ($expectedPayload) {
            $this->assertArraySubset($expectedPayload, $payload, true);

            $this->assertTrue($halt);

            return true;
        });

        Event::assertDispatched(sprintf('eloquent.attached: %s', User::class), function ($event, $payload) use ($expectedPayload) {
            $this->assertArraySubset($expectedPayload, $payload, true);

            return true;
        });
    }

    /**
     * @return array
     */
    public function attachProvider(): array
    {
        return [
            [
                // Id
                1,

                // Attributes
                [],

                // Expected payload
                [
                    'relation' => 'awards',
                    'data'     => [
                        1 => [],
                    ],
                ],
            ],

            [
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
                    'relation' => 'awards',
                    'data'     => [
                        2 => [
                            'prize' => 1024,
                        ],
                    ],
                ],
            ],

            [
                // Id
                [
                    2 => [
                        'prize' => 4096,
                    ],
                    1 => [
                        'prize' => 8192,
                    ],
                ],

                // Attributes
                [],

                // Expected payload
                [
                    'relation' => 'awards',
                    'data'     => [
                        2 => [
                            'prize' => 4096,
                        ],
                        1 => [
                            'prize' => 8192,
                        ],
                    ],
                ],
            ],

            [
                // Id
                Model::class,

                // Attributes
                [],

                // Expected payload
                [
                    'relation' => 'awards',
                    'data'     => [
                        1 => [],
                    ],
                ],
            ],

            [
                // Id
                Collection::class,

                // Attributes
                [],

                // Expected payload
                [
                    'relation' => 'awards',
                    'data'     => [
                        1 => [],
                        2 => [],
                    ],
                ],
            ],

            [
                // Id
                BaseCollection::make(1),

                // Attributes
                [],

                // Expected payload
                [
                    'relation' => 'awards',
                    'data'     => [
                        1 => [],
                    ],
                ],
            ],

            [
                // Id
                BaseCollection::make([
                    2,
                    1,
                ]),

                // Attributes
                [],

                // Expected payload
                [
                    'relation' => 'awards',
                    'data'     => [
                        2 => [],
                        1 => [],
                    ],
                ],
            ],
        ];
    }
}