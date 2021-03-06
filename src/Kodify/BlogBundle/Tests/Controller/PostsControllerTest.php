<?php

namespace Kodify\BlogBundle\Tests\Controller;

use Kodify\BlogBundle\Entity\Post;
use Kodify\BlogBundle\Entity\Author;
use Kodify\BlogBundle\Services\PostRater;
use Kodify\BlogBundle\Tests\BaseFunctionalTest;
use Symfony\Component\DomCrawler\Crawler;

class PostsControllerTest extends BaseFunctionalTest
{
    public function testIndexNoPosts()
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertTextFound($crawler, "There are no posts, let's create some!!");
    }

    /**
     * @dataProvider countDataProvider
     */
    public function testIndexWithPosts($postsToCreate, $countToCheck)
    {
        $this->createPosts($postsToCreate);
        $crawler = $this->client->request('GET', '/');
        $this->assertTextNotFound(
            $crawler,
            "There are no posts, let's create some!!",
            'Empty list found, it should have posts'
        );

        $this->assertSame(
            $countToCheck,
            substr_count($crawler->html(), 'by: Author'),
            "We should find $countToCheck messages from the author"
        );
        for ($i = 0; $i < $countToCheck; ++$i) {
            $this->assertTextFound($crawler, "Title{$i}");
            $this->assertTextFound($crawler, "Content{$i}");
        }
    }

    public function testViewNonExistingPost()
    {
        $crawler = $this->client->request('GET', '/posts/1');
        $this->assertTextFound($crawler, 'Post not found', 1);
    }

    public function testViewPost()
    {
        $this->createPosts(2);
        $crawler = $this->client->request('GET', '/posts/1');
        $this->assertTextFound($crawler, 'Title0');
        $this->assertTextFound($crawler, 'Content0');
        $this->assertTextNotFound($crawler, 'Title1');
        $this->assertTextNotFound($crawler, 'Content1');
    }

    public function testNoRatedPost()
    {
        $this->createPosts(1);
        $crawler = $this->client->request('GET', '/posts/1');
        $this->assertTextFound($crawler, 'No ratings');
    }

    public function testPostRate()
    {
        $this->createPosts(1);
        $crawler = $this->client->request('GET', '/posts/1');
        $this->assertTextFound($crawler, 'No ratings');
        $buttonCrawlerNode = $crawler->selectButton('Rate');
        $form = $buttonCrawlerNode->form(array('post_rating[rating]' => 5));
        $crawler = $this->client->submit($form);
        $this->assertTextFound($crawler, 'Rating: 5');
        $form = $buttonCrawlerNode->form(array('post_rating[rating]' => 3));
        $crawler = $this->client->submit($form);
        $this->assertTextFound($crawler, 'Rating: 4');
    }

    public function testOrderByRating()
    {
        $author = $this->createAuthor();
        for ($i = 0; $i < 3; ++$i) {
            $post = new Post();
            $post->setTitle('Title'.$i);
            $post->setContent('Content'.$i);
            $post->setAuthor($author);
            $this->entityManager()->persist($post);
            $postRater = new PostRater($this->entityManager());
            $postRater->rate($post, $i);
        }
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/');

        $nodeValues = $crawler->filter('.postTitle')->each(function (Crawler $node, $i) {
            return $node->text();
        });

        $this->assertEquals(array('Title0', 'Title1', 'Title2'), $nodeValues);

        $link = $crawler->selectLink('Order by rating')->link();
        $crawler = $this->client->click($link);

        $nodeValues = $crawler->filter('.postTitle')->each(function (Crawler $node, $i) {
            return $node->text();
        });

        $this->assertEquals(array('Title2', 'Title1', 'Title0'), $nodeValues);
    }

    protected function createPosts($count)
    {
        $author = $this->createAuthor();
        for ($i = 0; $i < $count; ++$i) {
            $post = new Post();
            $post->setTitle('Title'.$i);
            $post->setContent('Content'.$i);
            $post->setAuthor($author);
            $this->entityManager()->persist($post);
        }
        $this->entityManager()->flush();
    }

    public function countDataProvider()
    {
        $rand = rand(1, 5);

        return [
            'lessThanLimit' => ['count' => $rand, 'expectedCount' => $rand],
            'moreThanLimit' => ['count' => rand(6, 9), 'expectedCount' => 5],
        ];
    }

    /**
     * @return Author
     */
    protected function createAuthor()
    {
        $author = new Author();
        $author->setName('Author');
        $this->entityManager()->persist($author);
        $this->entityManager()->flush();

        return $author;
    }
}
