<?php

declare(strict_types=1);

namespace Altek\Eventually\Tests\Integration\BelongsToMany;

use Altek\Eventually\Tests\EventuallyTestCase;
use Altek\Eventually\Tests\Models\Article;
use Altek\Eventually\Tests\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Event;

class ToggleTest extends EventuallyTestCase
{
    /**
     * @test
     */
    public function itSuccessfullyRegistersEventListeners(): void
    {
        User::toggling(function ($user, $relation, $properties) {
            $this->assertInstanceOf(User::class, $user);

            $this->assertSame('articles', $relation);

            $this->assertSame([
                [
                    'user_id'    => 1,
                    'article_id' => 1,
                ],
            ], $properties);
        });

        User::toggled(function ($user, $relation, $properties) {
            $this->assertInstanceOf(User::class, $user);

            $this->assertSame('articles', $relation);

            $this->assertSame([
                [
                    'user_id'    => 1,
                    'article_id' => 1,
                ],
            ], $properties);
        });

        $user    = factory(User::class)->create();
        $article = factory(Article::class)->create();

        $this->assertCount(0, $user->articles()->get());

        $this->assertSame([
            'attached' => [
                1,
            ],
            'detached' => [],
        ], $user->articles()->toggle($article));

        $this->assertCount(1, $user->articles()->get());
    }

    /**
     * @test
     */
    public function itPreventsModelsFromBeingToggled(): void
    {
        User::toggling(function () {
            return false;
        });

        $user     = factory(User::class)->create();
        $articles = factory(Article::class, 2)->create();

        $this->assertCount(0, $user->articles()->get());

        $this->assertFalse($user->articles()->toggle($articles));

        $this->assertCount(0, $user->articles()->get());
    }

    /**
     * @test
     * @dataProvider toggleProvider
     *
     * @param array $results
     * @param mixed $id
     * @param array $expectedPayload
     */
    public function itSuccessfullyTogglesModels(array $results, $id, array $expectedPayload): void
    {
        $user     = factory(User::class)->create();
        $articles = factory(Article::class, 2)->create();

        $this->assertCount(0, $user->articles()->get());

        Event::fake();

        switch ($id) {
            case Model::class:
                $id = $articles->first();
                break;

            case Collection::class:
                $id = $articles;
                break;
        }

        $this->assertSame($results, $user->articles()->toggle($id));

        Event::assertDispatched(\sprintf('eloquent.toggling: %s', User::class), function ($event, $payload, $halt) use ($expectedPayload) {
            $this->assertInstanceOf(User::class, $payload[0]);

            unset($payload[0]);

            $this->assertSame($expectedPayload, $payload);

            $this->assertTrue($halt);

            return true;
        });

        Event::assertDispatched(\sprintf('eloquent.toggled: %s', User::class), function ($event, $payload) use ($expectedPayload) {
            $this->assertInstanceOf(User::class, $payload[0]);

            unset($payload[0]);

            $this->assertSame($expectedPayload, $payload);

            return true;
        });
    }

    /**
     * @return array
     */
    public function toggleProvider(): array
    {
        return [
            [
                // Results
                [
                    'attached' => [
                        1,
                    ],
                    'detached' => [],
                ],

                // Id
                1,

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 1,
                        ],
                    ],
                ],
            ],

            [
                // Results
                [
                    'attached' => [
                        2,
                    ],
                    'detached' => [],
                ],

                // Id
                [
                    2,
                ],

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 2,
                        ],
                    ],
                ],
            ],

            [
                // Results
                [
                    'attached' => [
                        2,
                        1,
                    ],
                    'detached' => [],
                ],

                // Id
                [
                    2,
                    1,
                ],

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 2,
                        ],
                        [
                            'user_id'    => 1,
                            'article_id' => 1,
                        ],
                    ],
                ],
            ],

            [
                // Results
                [
                    'attached' => [
                        1,
                    ],
                    'detached' => [],
                ],

                // Id
                Model::class,

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 1,
                        ],
                    ],
                ],
            ],

            [
                // Results
                [
                    'attached' => [
                        1,
                        2,
                    ],
                    'detached' => [],
                ],

                // Id
                Collection::class,

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 1,
                        ],
                        [
                            'user_id'    => 1,
                            'article_id' => 2,
                        ],
                    ],
                ],
            ],

            [
                // Results
                [
                    'attached' => [
                        1,
                    ],
                    'detached' => [],
                ],

                // Id
                BaseCollection::make(1),

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 1,
                        ],
                    ],
                ],
            ],

            [
                // Results
                [
                    'attached' => [
                        2,
                        1,
                    ],
                    'detached' => [],
                ],

                // Id
                BaseCollection::make([
                    2,
                    1,
                ]),

                // Expected payload
                [
                    1 => 'articles',
                    2 => [
                        [
                            'user_id'    => 1,
                            'article_id' => 2,
                        ],
                        [
                            'user_id'    => 1,
                            'article_id' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }
}
