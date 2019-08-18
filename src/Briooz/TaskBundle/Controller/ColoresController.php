<?php

namespace Briooz\TaskBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Briooz\TaskBundle\Entity\Color;

class ColoresController extends Controller {

    public function indexAction(Request $request) {

        $em = $this->getDoctrine()->getManager();

        $dql = "SELECT u FROM BrioozTaskBundle:Color u  ORDER BY u.descripcion ASC";

        $colores = $em->createQuery($dql);
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
                $colores, $request->query->getInt('page', 1), 100
        );

        $delete_form_ajax = $this->createCustomForm('COLOR_ID', 'DELETE', 'briooz_color_delete');

        return $this->render('BrioozTaskBundle:Colores:index.html.twig', array('colores' => $pagination, 'delete_form_ajax' => $delete_form_ajax->createView()));
    }

    public function addAction() {

        return $this->render('BrioozTaskBundle:Colores:add.html.twig');
    }

    public function editAction($id) {
        $em = $this->getDoctrine()->getManager();

        $color = $em->getRepository('BrioozTaskBundle:Color')->find($id);

        return $this->render('BrioozTaskBundle:Colores:edit.html.twig', array('color' => $color));
    }

    public function updateAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $idColor = $request->get('idcolor');
        $colorNew = $request->get('color');

        $color = $em->getRepository('BrioozTaskBundle:Color')->find($idColor);

        if ($color != null) {
            $color->setDescripcion($colorNew);
            $em->persist($color);
            $em->flush();
        }

        return $this->redirectToRoute('briooz_color_index');
    }

    public function creadoAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $color = $request->get('color');
        $colorObj = new Color();
        $colorObj->setDescripcion($color);

        $em->persist($colorObj);
        $em->flush();

        return $this->redirectToRoute('briooz_color_index');
    }

    public function deleteAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $color = $em->getRepository('BrioozTaskBundle:Color')->find($request->get('id'));

        if ($color != null) {
            $em->remove($color);
            $em->flush();
        }

        $cantidad = count($em->getRepository('BrioozTaskBundle:Color')->findAll());
        return new Response(
                json_encode(array('cantidad' => $cantidad)), 200, array('Content-Type' => 'application/json')
        );
    }

    private function createCustomForm($id, $method, $route) {
        return $this->createFormBuilder()
                        ->setAction($this->generateUrl($route, array('id' => $id)))
                        ->setMethod($method)
                        ->getForm();
    }

}
