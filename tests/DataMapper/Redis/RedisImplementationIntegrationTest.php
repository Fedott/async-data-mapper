<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Redis\Client;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\IdentityMap;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\Redis\FetchManager;
use Fedot\DataMapper\Redis\KeyGenerator;
use Fedot\DataMapper\Redis\ModelManager;
use Fedot\DataMapper\Redis\PersistManager;
use Metadata\MetadataFactory;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Fedot\DataMapper\Stubs\Identifiable;
use Tests\Fedot\DataMapper\Stubs\Integration\Author;
use Tests\Fedot\DataMapper\Stubs\Integration\AuthorBio;
use Tests\Fedot\DataMapper\Stubs\Integration\Book;
use Tests\Fedot\DataMapper\Stubs\Integration\Genre;
use Tests\Fedot\DataMapper\Stubs\Integration\SimpleModel;
use function Amp\all;
use function Amp\wait;

class RedisImplementationIntegrationTest extends RedisImplementationTestCase
{
    /**
     * @var Client
     */
    private $redisClient;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        print `redis-server --daemonize yes --port 25325 --timeout 333 --pidfile /tmp/amp-redis.pid`;
//        sleep(1);
    }

    public static function tearDownAfterClass()
    {
        $pid = @file_get_contents('/tmp/amp-redis.pid');
        @unlink('/tmp/amp-redis.pid');

        if (!empty($pid)) {
            print `kill $pid`;
        }
    }

    public function testPersist()
    {
        $client = new Client('tcp://localhost:25325?database=7');

        $serializer     = new Serializer(
            [
                new ObjectNormalizer(null, null, null, new PropertyInfoExtractor()),
            ], [
            new JsonEncoder(),
        ]
        );
        $keyGenerator   = new KeyGenerator();

        $persistManager = new PersistManager($keyGenerator, $client, $serializer);
        $fetchManager = new FetchManager($keyGenerator, $client, $serializer);

        $model = new Identifiable('test');

        wait($persistManager->persist($model));

        $result = wait($fetchManager->fetchById(Identifiable::class, 'test'));

        $this->assertEquals($model, $result);

        $client->close();
    }

    public function testSimpleModel()
    {
        $model = new SimpleModel('simple-model-id1', 'field 1');
        $model->setField2('field 2 value');

        $modelManager = $this->getModelManager();

        wait($modelManager->persist($model));

        /** @var SimpleModel $loadedModel */
        $loadedModel = wait($modelManager->find(SimpleModel::class, 'simple-model-id1'));

        $this->assertEquals($model->getId(), $loadedModel->getId());
        $this->assertEquals($model->getField1(), $loadedModel->getField1());
        $this->assertNull($loadedModel->getField2());

        $this->redisClient->close();
    }

    public function testBooks()
    {
        $genreSince = new Genre('genre-id1', 'Since');
        $genreFantasy = new Genre('genre-id2', 'Fantasy');
        $genreEmpty = new Genre('genre-id3', 'Empty');

        $author1 = new Author('author-id1', 'Author First');
        $author2 = new Author('author-id2', 'Second Author');
        $author3 = new Author('author-id3', 'Empty Author');

        $bio1 = new AuthorBio('bio-id1', $author1, 'Bio 1');
        $bio2 = new AuthorBio('bio-id2', $author2, 'Bio 2');

        $book1 = new Book('book-id1', 'Book 1', $author1, [$genreSince, $genreFantasy]);
        $book2 = new Book('book-id2', 'Book 2', $author1, [$genreFantasy]);
        $book3 = new Book('book-id3', 'Book 3', $author1, [$genreSince]);

        $book4 = new Book('book-id4', 'Book 4', $author2);
        $book5 = new Book('book-id5', 'Book 5', $author2, [$genreSince]);


        $modelManager = $this->getModelManager();
        $identityMap = new IdentityMap();

        wait(all([
            $modelManager->persist($genreSince, $identityMap),
            $modelManager->persist($genreFantasy, $identityMap),
            $modelManager->persist($genreEmpty, $identityMap),

            $modelManager->persist($author1, $identityMap),
            $modelManager->persist($author2, $identityMap),
            $modelManager->persist($author3, $identityMap),

            $modelManager->persist($bio1, $identityMap),
            $modelManager->persist($bio2, $identityMap),

            $modelManager->persist($book1, $identityMap),
            $modelManager->persist($book2, $identityMap),
            $modelManager->persist($book3, $identityMap),
            $modelManager->persist($book4, $identityMap),
            $modelManager->persist($book5, $identityMap),
        ]));

        /** @var Author $loadedAuthor2 */
        $loadedAuthor2 = wait($modelManager->find(Author::class, 'author-id2', 1, $identityMap));

        $this->assertEquals('Second Author', $loadedAuthor2->getName());
        $this->assertEquals($bio2->getId(), $loadedAuthor2->getBio()->getId());
        $this->assertEquals($bio2->getContent(), $loadedAuthor2->getBio()->getContent());
        $this->assertCount(2, $loadedAuthor2->getBooks());

        /** @var Author $loadedAuthor1 */
        $loadedAuthor1 = wait($modelManager->find(Author::class, 'author-id1', 1, $identityMap));

        $this->assertEquals('Author First', $loadedAuthor1->getName());
        $this->assertEquals($bio1->getId(), $loadedAuthor1->getBio()->getId());
        $this->assertEquals($bio1->getContent(), $loadedAuthor1->getBio()->getContent());
        $this->assertCount(3, $loadedAuthor1->getBooks());

        /** @var Book $loadedBook1 */
        $loadedBook1 = wait($modelManager->find(Book::class, 'book-id1', 1, $identityMap));

        $this->assertEquals('book-id1', $loadedBook1->getId());
        $this->assertEquals('Author First', $loadedBook1->getAuthor()->getName());
        $this->assertCount(2, $loadedBook1->getGenres());
        $this->assertSame($loadedAuthor1, $loadedBook1->getAuthor());

        $this->assertSame($author1, $loadedAuthor1);

        /** @var Genre[] $expectedGenres */
        $expectedGenres = [
            $genreSince,
            $genreFantasy,
        ];

        foreach ($expectedGenres as $expectedGenre) {
            $found = false;

            foreach ($loadedBook1->getGenres() as $genre) {
                if ($expectedGenre->getId() === $genre->getId()) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found, 'Not found genres');
        }

        $loadedBook4 = wait($modelManager->find(Book::class, 'book-id4', 1, $identityMap));
        $this->assertSame($book4, $loadedBook4);

        wait($modelManager->remove($book4, $identityMap));

        $loadedBook4AfterRemove = wait($modelManager->find(Book::class, 'book-id4', 1, $identityMap));
        $this->assertNull($loadedBook4AfterRemove);

        wait($modelManager->remove($book5));
        $this->assertNull(wait($modelManager->find(Book::class, 'book-id5')));

        $identityMap->clear();

        $loadedAuthor1AfterClear = wait($modelManager->find(Author::class, 'author-id1', 1, $identityMap));
        $this->assertNotSame($loadedAuthor1, $loadedAuthor1AfterClear);

        $this->redisClient->close();
    }

    private function getModelManager(): ModelManager
    {
        $this->redisClient = new Client('tcp://localhost:25325?database=7');

        $propertyAccessor = new PropertyAccessor();
        $modelManager = new ModelManager(
            new MetadataFactory(new AnnotationDriver(new AnnotationReader())), $this->redisClient,
            $propertyAccessor,
            new Instantiator()
        );

        return $modelManager;
    }
}
