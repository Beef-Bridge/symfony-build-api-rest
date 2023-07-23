<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'api_authors_get', methods: ['GET'])]
    public function getAuthorList(AuthorRepository $authorRepository, SerializerInterface $serializer): JsonResponse
    {
        $authorList = $authorRepository->findAll();
        $jsonAuthorList = $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors/{id}', name: 'api_detail_author_get', methods: ['GET'])]
    public function getDetailBook(
        Author $author,
        SerializerInterface $serializer
    ): JsonResponse {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, ['accept' => 'json'], true);
    }
}
