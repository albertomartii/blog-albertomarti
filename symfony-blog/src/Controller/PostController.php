<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Post;
use App\Form\PostFormType;

final class PostController extends AbstractController
{
    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            // Quitamos los caracteres especiales del título para crear el slug
            $post->setSlug($slugger->slug($post->getTitle()));

            // Guardamos el usuario que crea el post y los contadores a 0
            $post->setPostUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);

            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();
            return $this->redirectToRoute('single_post', ['slug' => $post->getSlug()]);
        }
        return $this->render('blog/new_post.html.twig', array(
            'form' => $form->createView()
        ));
    }
    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["slug" => $slug]);
        $recents = $repository->findRecents();
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $comment->setPost($post);
            //Aumentamos en 1 el número de comentarios del post
            $post->setNumComments($post->getNumComments() + 1);
            $entityManager = $doctrine->getManager();
            $entityManager->persist($comment);
            $entityManager->flush();
            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }
        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
            'commentForm' => $form->createView()
        ]);
    }
    private const POSTS_PER_PAGE = 5; //Ponlo a 1 para pruebas

    #[Route('/blog/{page}', name: 'blog')]
    public function index(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $page = max(1, $page);

        $repository = $doctrine->getRepository(Post::class);
        //Aquí obtenemos los datos con el método creado en el repositorio
        $posts = $repository->findPaginados($page, self::POSTS_PER_PAGE);
        $recents = $repository->findRecents();

        $total = count($posts);
        $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));

        // Si piden una página que no existe, redirigimos a la última
        if ($page > $totalPages) {
            return $this->redirectToRoute('blog', ['page' => $totalPages]);
        }

        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/blog/buscar/{page}', name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine, Request $request, int $page = 1): Response
    {
        $page = max(1, $page);

        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get('searchTerm', '');
        $posts = $repository->findBuscadosPaginados($searchTerm, $page, self::POSTS_PER_PAGE);
        $recents = $repository->findRecents();

        $total = count($posts);
        $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));

        if ($page > $totalPages) {
            return $this->redirectToRoute('blog_buscar', ['page' => $totalPages, 'searchTerm' => $searchTerm]);
        }

        return $this->render('blog/blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
            'searchTerm' => $searchTerm,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/single_post/{slug}/like', name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug): JsonResponse
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["slug" => $slug]);
        if ($post) {
            $post->like();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();
        }
        $data = ['success' => true, 'likes' => $post->getNumLikes()];
        return $this->json($data);
    }


}