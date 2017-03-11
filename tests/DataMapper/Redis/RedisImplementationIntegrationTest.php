<?php declare(strict_types=1);

namespace Tests\Fedot\DataMapper\Redis;

use Amp\Redis\Client;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Instantiator\Instantiator;
use Fedot\DataMapper\Metadata\Driver\AnnotationDriver;
use Fedot\DataMapper\Redis\FetchManager;
use Fedot\DataMapper\Redis\KeyGenerator;
use Fedot\DataMapper\Redis\ModelManager;
use Fedot\DataMapper\Redis\PersistManager;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
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

        print `redis-server --daemonize yes --port 25325 --timeout 3 --pidfile /tmp/amp-redis.pid`;
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

        wait(all([
            $modelManager->persist($genreSince),
            $modelManager->persist($genreFantasy),
            $modelManager->persist($genreEmpty),

            $modelManager->persist($author1),
            $modelManager->persist($author2),
            $modelManager->persist($author3),

            $modelManager->persist($bio1),
            $modelManager->persist($bio2),

            $modelManager->persist($book1),
            $modelManager->persist($book2),
            $modelManager->persist($book3),
            $modelManager->persist($book4),
            $modelManager->persist($book5),
        ]));

        /** @var Author $loadedAuthor2 */
        $loadedAuthor2 = wait($modelManager->find(Author::class, 'author-id2'));

        $this->assertEquals('Second Author', $loadedAuthor2->getName());
        $this->assertEquals($bio2->getId(), $loadedAuthor2->getBio()->getId());
        $this->assertEquals($bio2->getContent(), $loadedAuthor2->getBio()->getContent());
        $this->assertCount(2, $loadedAuthor2->getBooks());

        /** @var Book $loadedBook1 */
        $loadedBook1 = wait($modelManager->find(Book::class, 'book-id1'));

        $this->assertEquals('book-id1', $loadedBook1->getId());
        $this->assertEquals('Author First', $loadedBook1->getAuthor()->getName());
        $this->assertCount(2, $loadedBook1->getGenres());

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
