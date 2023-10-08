<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OAT;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[OAT\Tag(name: 'books')]
class BookController extends AbstractController
{
    /**
     * @throws InvalidArgumentException
     */
    #[Route('/api/books', name: 'api_books_get', methods: ['GET'])]
    #[OAT\Get(
        path: "/api/books",
        summary: "Liste des livres",
        description: "Retourne la liste de tous les livres sans distinction",
        operationId: "getBookList"
    )]
    #[OAT\Parameter(
        name: "authorization",
        in: "header",
        required: true,
        description: "Authorization",
        schema: new OAT\Schema(type: "string", default: "Bearer TOKEN")
    )]
    #[OAT\Parameter(
        name: "page",
        in: "query",
        description: "La page que l'on veut récupérer",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Parameter(
        name: "limit",
        in: "query",
        description: "Le nombre d'éléments que l'on veut récupérer",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Response(
        response: Response::HTTP_OK,
        description: 'OK',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Book::class))
        )
    )]
    #[OAT\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Requires authentication'
    )]
    #[OAT\Response(
        response: Response::HTTP_FORBIDDEN,
        description: 'Forbidden'
    )]
    #[Security(name: "Bearer")]
    public function getBookList(
        BookRepository $bookRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;
        $jsonBookList = $cache->get(
            $idCache,
            function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
                $item->tag("booksCache");
                $bookList = $bookRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
            }
        );

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/books/{id}', name: 'api_detail_book_get', methods: ['GET'])]
    #[OAT\Get(
        path: "/api/books/{id}",
        summary: "Détails d'un livre",
        description: "Retourne les détails d'un livre",
        operationId: "getDetailBook"
    )]
    #[OAT\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Identifiant d'un livre",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Response(
        response: Response::HTTP_OK,
        description: 'OK',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Book::class))
        )
    )]
    #[OAT\Response(
        response: Response::HTTP_FORBIDDEN,
        description: 'Forbidden'
    )]
    #[OAT\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Resource not found'
    )]
    #[Security(name: "Bearer")]
    public function getDetailBook(
        Book $book,
        SerializerInterface $serializer
    ): JsonResponse {
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBook, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/api/books/{id}', name: 'api_books_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
    #[OAT\Delete(
        path: "/api/books/{id}",
        summary: "Suppression d'un livre",
        description: "Supprimer un livre",
        operationId: "deleteBook"
    )]
    #[OAT\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Identifiant d'un livre",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Response(
        response: Response::HTTP_NO_CONTENT,
        description: 'No Content'
    )]
    #[OAT\Response(
        response: Response::HTTP_FORBIDDEN,
        description: 'Forbidden'
    )]
    #[OAT\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Resource not found'
    )]
    #[Security(name: "Bearer")]
    public function deleteBook(
        Book $book,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $cachePool->invalidateTags(["booksCache"]);

        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name:"api_books_post", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    #[OAT\Post(
        path: "/api/books",
        summary: "Création d'un livre",
        description: "Créer un livre",
        operationId: "createBook"
    )]
    #[OAT\Response(
        response: Response::HTTP_CREATED,
        description: 'Created',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Book::class))
        )
    )]
    #[OAT\Response(
        response: Response::HTTP_FORBIDDEN,
        description: 'Forbidden'
    )]
    #[Security(name: "Bearer")]
    public function createBook(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        $location = $urlGenerator->generate(
            'api_detail_book_get',
            ['id' => $book->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse(
            $jsonBook,
            Response::HTTP_CREATED,
            ["Location" => $location],
            true
        );
    }

    #[Route('/api/books/{id}', name:"api_books_put", methods:['PUT'])]
    #[OAT\Put(
        path: "/api/books/{id}",
        summary: "Mise à jour d'un livre",
        description: "Mettre à jour un livre",
        operationId: "updateBook"
    )]
    #[OAT\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Identifiant d'un livre",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Response(
        response: Response::HTTP_OK,
        description: 'OK',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Book::class))
        )
    )]
    #[OAT\Response(
        response: Response::HTTP_FORBIDDEN,
        description: 'Forbidden'
    )]
    #[OAT\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Resource not found'
    )]
    #[Security(name: "Bearer")]
    public function updateBook(
        Request $request,
        SerializerInterface $serializer,
        Book $currentBook,
        EntityManagerInterface $em,
        AuthorRepository $authorRepository
    ): JsonResponse {
        $updatedBook = $serializer->deserialize(
            $request->getContent(),
            Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]
        );
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $updatedBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($updatedBook);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
