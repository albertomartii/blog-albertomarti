<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    #[Route('/admin/categories', name: 'app_categories')]
    public function categories(ManagerRegistry $doctrine, Request $request): Response
    {
        $repositorio = $doctrine->getRepository(Category::class);
        $categories = $repositorio->findAll();

        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($category);
            $entityManager->flush();
            // redirigimos a la misma ruta para que se vea la nueva categoría añadida 
            // y no se repita el envío del formulario al recargar la página
            return $this->redirectToRoute('app_categories');
        }

        return $this->render('admin/categories.html.twig', array(
            'form' => $form->createView(),
            'categories' => $categories
        ));
    }


}