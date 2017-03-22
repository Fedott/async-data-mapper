<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Stubs\Integration;

use Fedot\DataMapper\Annotation\Field;
use Fedot\DataMapper\Annotation\Id;
use Fedot\DataMapper\Annotation\ReferenceMany;

class Genre
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
     * @var Book[]
     *
     * @ReferenceMany(target=Book::class)
     */
    private $books;

    /**
     * @var Author[]
     *
     * @ReferenceMany(target=Author::class)
     */
    private $authors;

    public function __construct(string $id, string $name, array $books = [], array $authors = [])
    {
        $this->id      = $id;
        $this->name    = $name;
        $this->books   = $books;
        $this->authors = $authors;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBooks(): array
    {
        return $this->books;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function markBook(Book $book)
    {
        if (!in_array($book, $this->books, true)) {
            $this->books[] = $book;
        }
    }
}
