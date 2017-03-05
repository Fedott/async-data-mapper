<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Stubs\Integration;

use Fedot\DataMapper\Annotation\Field;
use Fedot\DataMapper\Annotation\Id;
use Fedot\DataMapper\Annotation\ReferenceMany;
use Fedot\DataMapper\Annotation\ReferenceOne;

class Book
{
    /**
     * @var string
     *
     * @Id
     */
    private $id;

    /**
     * @var string
     *
     * @Field
     */
    private $title;

    /**
     * @var Author
     *
     * @ReferenceOne(target=Author::class)
     */
    private $author;

    /**
     * @var Genre[]
     *
     * @ReferenceMany(target=Genre::class)
     */
    private $genres;

    public function __construct(string $id, string $title, Author $author, array $genres = [])
    {
        $this->id     = $id;
        $this->title  = $title;
        $this->author = $author;
        $this->genres = $genres;

        $author->writeBook($this);

        /** @var Genre $genre */
        foreach ($genres as $genre) {
            $genre->markBook($this);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }
}
