<?php

namespace Briooz\TaskBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Briooz\TaskBundle\Entity\Modelo;

class ModelosController extends Controller {

    public function indexAction(Request $request) {

        $em = $this->getDoctrine()->getManager();

        $dql = "SELECT u FROM BrioozTaskBundle:Modelo u  ORDER BY u.id ASC";

        $modelos = $em->createQuery($dql);
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
                $modelos, $request->query->getInt('page', 1), 25
        );

        $delete_form_ajax = $this->createCustomForm('MODELO_ID', 'DELETE', 'briooz_modelo_delete');

        return $this->render('BrioozTaskBundle:Modelos:index.html.twig', array('modelos' => $pagination, 'delete_form_ajax' => $delete_form_ajax->createView()));
    }

    public function addAction() {

        return $this->render('BrioozTaskBundle:Modelos:add.html.twig');
    }

    public function editAction($id) {
        $em = $this->getDoctrine()->getManager();

        $modelo = $em->getRepository('BrioozTaskBundle:Modelo')->find($id);

        return $this->render('BrioozTaskBundle:Modelos:edit.html.twig', array('modelo' => $modelo));
    }

    public function updateAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $idModelo = $request->get('idmodelo');
        $modeloNew = $request->get('modelo');

        $modelo = $em->getRepository('BrioozTaskBundle:Modelo')->find($idModelo);

        if ($modelo != null) {
            $modelo->setDescripcion($modeloNew);
            $em->persist($modelo);
            $em->flush();
        }

        return $this->redirectToRoute('briooz_modelo_index');
    }

    public function creadoAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $modelo = $request->get('modelo');
        $categoria=$request->get('categoria');
        $modeloObj = new Modelo();
        $modeloObj->setDescripcion($modelo);
        $modeloObj->setCategoria($categoria);

        $em->persist($modeloObj);
        $em->flush();

        return $this->redirectToRoute('briooz_modelo_index');
    }

    public function deleteAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $modelo = $em->getRepository('BrioozTaskBundle:Modelo')->find($request->get('id'));

        if ($modelo != null) {
            $em->remove($modelo);
            $em->flush();
        }

        $cantidad = count($em->getRepository('BrioozTaskBundle:Modelo')->findAll());
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
