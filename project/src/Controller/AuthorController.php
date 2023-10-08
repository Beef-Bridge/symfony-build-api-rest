<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[OA\Tag(name: 'authors')]
class AuthorController extends AbstractController
{
    /**
     * @throws InvalidArgumentException
     */
    #[Route('/api/authors', name: 'api_authors_get', methods: ['GET'])]
    #[OAT\Get(
        path: "/api/authors",
        summary: "Liste des auteurs",
        description: "Retourne la liste de tous les auteurs sans distinction",
        operationId: "getAuthorList"
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
            items: new OAT\Items(ref: new Model(type: Author::class))
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
    public function getAuthorList(
        AuthorRepository $authorRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllAuthor-" . $page . "-" . $limit;
        $jsonAuthorList = $cache->get(
            $idCache,
            function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
                //echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
                $item->tag("booksCache");
                $authorList = $authorRepository->findAllWithPagination($page, $limit);
                return $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);
            }
        );

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors/{id}', name: 'api_detail_author_get', methods: ['GET'])]
    #[OAT\Get(
        path: "/api/authors/{id}",
        summary: "Détails d'un auteur",
        description: "Retourne les détails d'un auteur",
        operationId: "getDetailBook"
    )]
    #[OAT\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Identifiant d'un auteur",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Response(
        response: Response::HTTP_OK,
        description: 'OK',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Author::class))
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
        Author $author,
        SerializerInterface $serializer
    ): JsonResponse {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/api/authors/{id}', name: 'api_authors_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un auteur')]
    #[OAT\Delete(
        path: "/api/authors/{id}",
        summary: "Suppression d'un auteur",
        description: "Supprimer un auteur",
        operationId: "deleteBook"
    )]
    #[OAT\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Identifiant d'un auteur",
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
        Author $author,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $em->remove($author);
        $em->flush();

        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors', name:"api_authors_post", methods: ['POST'])]
    #[OAT\Post(
        path: "/api/authors",
        summary: "Création d'un auteur",
        description: "Créer un auteur",
        operationId: "createAuthor"
    )]
    #[OAT\Response(
        response: Response::HTTP_CREATED,
        description: 'Created',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Author::class))
        )
    )]
    #[OAT\Response(
        response: Response::HTTP_FORBIDDEN,
        description: 'Forbidden'
    )]
    #[Security(name: "Bearer")]
    public function createAuthor(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator
    ): JsonResponse {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        $location = $urlGenerator->generate(
            'api_detail_author_get',
            ['id' => $author->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/authors/{id}', name:"api_authors_put", methods:['PUT'])]
    #[OAT\Put(
        path: "/api/authors/{id}",
        summary: "Mise à jour d'un auteur",
        description: "Mettre à jour un auteur",
        operationId: "updateAuthor"
    )]
    #[OAT\Parameter(
        name: "id",
        in: "path",
        required: true,
        description: "Identifiant d'un auteur",
        schema: new OAT\Schema(type: "int")
    )]
    #[OAT\Response(
        response: Response::HTTP_OK,
        description: 'OK',
        content: new OAT\JsonContent(
            type: 'array',
            items: new OAT\Items(ref: new Model(type: Author::class))
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
    public function updateAuthor(
        Request $request,
        SerializerInterface $serializer,
        Author $currentAuthor,
        EntityManagerInterface $em
    ): JsonResponse {

        $updatedAuthor = $serializer->deserialize(
            $request->getContent(),
            Author::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]
        );

        $em->persist($updatedAuthor);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
