<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Stubs\Integration;

use Fedot\DataMapper\Annotation\Field;
use Fedot\DataMapper\Annotation\Id;
use Fedot\DataMapper\Annotation\ReferenceMany;
use Fedot\DataMapper\Annotation\ReferenceOne;

class Author
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
    private $name;

    /**
     * @var AuthorBio
     *
     * @ReferenceOne(target=AuthorBio::class)
     */
    private $bio;

    /**
     * @var Book[]
     *
     * @ReferenceMany(target=Book::class)
     */
    private $books;

    /**
     * @var Genre[]
     *
     * @ReferenceMany(target=Genre::class)
     */
    private $genres;

    public function __construct(string $id, string $name, AuthorBio $bio = null, array $books = [], array $genres = [])
    {
        $this->id     = $id;
        $this->name   = $name;
        $this->bio    = $bio;
        $this->books  = $books;
        $this->genres = $genres;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBio(): ?AuthorBio
    {
        return $this->bio;
    }

    public function getBooks(): array
    {
        return $this->books;
    }

    public function getGenres(): array
    {
        return $this->genres;
    }

    public function writeBio(AuthorBio $bio)
    {
        $this->bio = $bio;
    }

    public function writeBook(Book $book)
    {
        if (!in_array($book, $this->books, true)) {
            $this->books[] = $book;
        }
    }
}
