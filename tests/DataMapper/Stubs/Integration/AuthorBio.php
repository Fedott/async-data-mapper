<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Stubs\Integration;

use Fedot\DataMapper\Annotation\Field;
use Fedot\DataMapper\Annotation\Id;
use Fedot\DataMapper\Annotation\ReferenceOne;

class AuthorBio
{
    /**
     * @var string
     *
     * @Id
     */
    private $id;

    /**
     * @var Author
     *
     * @ReferenceOne(target=Author::class)
     */
    private $author;

    /**
     * @var string
     *
     * @Field
     */
    private $content;

    public function __construct(string $id, Author $author, string $content)
    {
        $this->id      = $id;
        $this->author  = $author;
        $this->content = $content;

        $author->writeBio($this);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
