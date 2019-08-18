<?php

namespace Briooz\VentasBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use \Briooz\TaskBundle\Entity\Producto;
use \Briooz\TaskBundle\Entity\Cliente;
use \Briooz\TaskBundle\Entity\ProductoTalla;
use \Briooz\VentasBundle\Entity\Venta;
use \Briooz\TaskBundle\Entity\VentaProducto;
use \Briooz\VentasBundle\Entity\VentaFormaPago;

class VentasController extends Controller {

    public function indexAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $dql = "SELECT u FROM BrioozVentasBundle:Venta u  ORDER BY u.fecha DESC";

        $ventas = $em->createQuery($dql);
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
                $ventas, $request->query->getInt('page', 1), 25
        );

        $delete_form_ajax = $this->createCustomForm('VENTA_ID', 'DELETE', 'briooz_venta_delete');

        return $this->render('BrioozVentasBundle:Ventas:index.html.twig', array('ventas' => $pagination, 'delete_form_ajax' => $delete_form_ajax->createView()));
    }

    public function addAction() {
        $em = $this->getDoctrine()->getManager();
        $iva = $this->container->getParameter('iva');

        //cargar las formas de pago
        $formaspago = $em->getRepository('BrioozTaskBundle:FormaPago')->findAll();
        $cantFormasPago = count($formaspago);

        return $this->render('BrioozVentasBundle:Ventas:add.html.twig', array('iva' => $iva, 'formaspago' => $formaspago, 'cantFormasPago' => $cantFormasPago));
    }

    public function addClienteAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $id = $request->get('id');

        $cliente = $em->getRepository('BrioozTaskBundle:Cliente')->find($id);

        $clienteR = array();
        if ($cliente != null) {
            $clienteR['id'] = $cliente->getId();
            $clienteR['nombres'] = $cliente->getNombres();
            $clienteR['apellidos'] = $cliente->getApellidos();
            $clienteR['celular'] = $cliente->getCelular();
            $clienteR['telefono'] = $cliente->getTelefono();
            $clienteR['ci'] = $cliente->getCi();
            $clienteR['ruc'] = $cliente->getRuc();
            $clienteR['direccion'] = $cliente->getDireccion();
            $clienteR['email'] = $cliente->getEmail();
        }

        return new Response(
                json_encode(array('cliente' => $clienteR)), 200, array('Content-Type' => 'application/json')
        );
    }

    public function addProductoAjaxAction(Request $request) {

        $em = $this->getDoctrine()->getManager();
        $id = $request->get('id');


        $producto = $em->getRepository('BrioozTaskBundle:Producto')->find($id);

        $producroR = array();
        if ($producto != null) {
            // $producto = new Producto();
            $producroR['id'] = $producto->getId();
            $producroR['codigointerno'] = $producto->getCodigoInterno();
            $producroR['producto'] = $producto->getModelo()->getDescripcion() . " " . $producto->getCategoria()->getDescripcion() . " - cod(" . $producto->getCodigoInterno() . ")";
            $producroR['precio'] = $producto->getPvp();
        }

        $iva = '0.' . $this->container->getParameter('iva');

        return new Response(
                json_encode(array('producto' => $producroR, 'iva' => $iva)), 200, array('Content-Type' => 'application/json')
        );
    }

    public function cargarFacturaAjaxAction(Request $requet) {
        $em = $this->getDoctrine()->getManager();
        $id = $requet->get('id');

        $venta = $em->getRepository('BrioozVentasBundle:Venta')->find($id);

        $ventaResult = array();
        //$venta = new Venta();
        if ($venta != null) {
            $ventaResult['id'] = $venta->getId();
            $ventaResult['tipocliente'] = $venta->getCliente()->getTipoCliente();

            $cliente = $venta->getCliente();

            if ($venta->getCliente()->getTipoCliente() == "PERSONA") {
                $ventaResult['nombre'] = $cliente->getNombres() . " " . $cliente->getApellidos();
                $ventaResult['ci'] = $cliente->getCi();
            } else {
                $ventaResult['razonsocial'] = $cliente->getNombreEmpresa();
                $ventaResult['ruc'] = $cliente->getRuc();
            }

            $ventaResult['fecha'] = $venta->getFecha()->format('Y-m-d');
            $ventaResult['hora'] = $venta->getHora()->format('H:i:s');

            $vendedor = $venta->getUsuario();
            $ventaResult['vendedor'] = $vendedor->getNombre() . " " . $vendedor->getApellidos();
            $ventaResult['direccion'] = $cliente->getDireccion();

            //buscar los productos de la venta
            $ventaproductos = $em->getRepository('BrioozTaskBundle:VentaProducto')->findBy(array('venta' => $venta->getid()));

            $productos = array();

            $pro = array();
            $proResult = array();
            foreach ($ventaproductos as $vp) {
                $proR = $em->getRepository('BrioozTaskBundle:Producto')->find($vp->getProducto()->getId());
                $pro['producto'] = "[" . $proR->getCodigoInterno() . "] " . $proR->getModelo()->getDescripcion() . " " . $proR->getCategoria()->getDescripcion();
                $pro['cantidad'] = $vp->getCantidad();
                $pro['descuento'] = $vp->getDescuento();
                $pro['unitario'] = $proR->getPvp();
                $pro['total'] = $vp->getTotal();

                if ($pro != null) {
                    $proResult[] = $pro;
                }
            }

            //datos de los costos de la factura
            $ventaResult['cantproductos'] = $venta->getCantproductos();
            $ventaResult['subtotal'] = $venta->getSubTotal();
            $ventaResult['iva'] = $venta->getIva();
            $ventaResult['descuentos'] = $venta->getDescuentos();
            $ventaResult['fijos'] = $venta->getDescuentoFijo();
            $ventaResult['total'] = $venta->getTotal();
        }


        return new Response(
                json_encode(array('id' => $id, 'venta' => $ventaResult, 'productos' => $proResult)), 200, array('Content-Type' => 'application/json')
        );
    }

    public function buscarclienteAction(Request $requet) {
        $em = $this->getDoctrine()->getManager();

        $filtro = $requet->get('filtro_cliente');

        $dql = "SELECT u FROM BrioozTaskBundle:Cliente u  WHERE u.ci LIKE :filtro or u.nombres LIKE :filtro or u.apellidos LIKE :filtro ORDER BY u.id DESC";
        $parameters = array('filtro' => '%' . $filtro . '%');
        $consulta = $em->createQuery($dql)->setMaxResults(4);
        $consulta->setParameters($parameters);

        $clientes = $consulta->getResult();

        $cliente = array();
        $clientesResult = array();

        if (count($clientes) > 0) {
            foreach ($clientes as $cl) {

                $cliente['id'] = $cl->getId();
                $cliente['nombres'] = $cl->getNombres();
                $cliente['apellidos'] = $cl->getApellidos();
                $cliente['ci'] = $cl->getCi();
                $cliente['ruc'] = $cl->getRuc();

                $clientesResult[] = $cliente;
            }
        }


        return new Response(
                json_encode(array('cantidad' => count($clientesResult), 'clientes' => $clientesResult)), 200, array('Content-Type' => 'application/json')
        );
    }

    public function buscarProductoAction(Request $requet) {
        $em = $this->getDoctrine()->getManager();

        $codigo = $requet->get('filtro_busqueda');
        //$codigo = "JNN2KBN";

        $dql = "SELECT u FROM BrioozTaskBundle:Producto u  WHERE u.codigoInterno LIKE :codigo ORDER BY u.id DESC";
        $parameters = array('codigo' => '%' . $codigo . '%');

        $consulta = $em->createQuery($dql);
        $consulta->setParameters($parameters);

        $productos = $consulta->getResult();

        $producto = array();
        $productosResult = array();
        $cantidadTallas = array();
        $alltallas = array();

        if ($productos != null) {
            foreach ($productos as $pr) {

                $producto['id'] = $pr->getId();
                $producto['codigo'] = $pr->getCodigoInterno();
                $producto['total'] = $pr->getTotal();
                $producto['pvp'] = $pr->getPvp();
                $producto['producto'] = $pr->getModelo()->getDescripcion() . " " . $pr->getCategoria()->getDescripcion() . "";

                $productosResult[] = $producto;

                $proTallas = $em->getRepository('BrioozTaskBundle:ProductoTalla')->findBy(array('producto' => $pr->getId()));

                foreach ($proTallas as $proT) {
                    $alltallas[] = $proT->getTalla();
                    $cantidadTallas[$proT->getTalla()->getId()] = $proT->getCantidad();
                }
            }
        }


        if ($codigo == "") {
            $productosResult = array();
        }


        //  $alltallas=$em->getRepository('BrioozTaskBundle:Talla')->findAll();

        $tallas = array();
        $tallasResult = array();
        if (count($alltallas) > 0) {
            foreach ($alltallas as $talla) {

                $tallas['id'] = $talla->getId();
                $tallas['descripcion'] = $talla->getDescripcion();
                $tallas['cantidad'] = $cantidadTallas[$talla->getId()];
                $tallasResult[] = $tallas;
            }
        }


        return new Response(
                json_encode(array('productos' => $productosResult, 'tallas' => $tallasResult)), 200, array('Content-Type' => 'application/json')
        );
    }

    public function editAction($id) {
        
    }

    public function updateAction(Request $request) {
        
    }

    //crear la venta
    public function creadoAction(Request $request) {
        $em = $this->getDoctrine()->getManager();

        $usuario = $this->getUser();
        $cliente = $em->getRepository('BrioozTaskBundle:Cliente')->find($request->get('id_cliente'));

        $descuentoall = $request->get('descuentoall');
        $total_venta = $request->get('total_venta');
        $subtotal_venta = $request->get('subtotal_venta');
        $subtotal_iva = $request->get('subtotal_iva');
        $nofactura = $request->get('nofactura');
        $descuentofijo = $request->get('descuentofijo');
        $item_selected = $request->get('item_selected');

        $fecha = date('Y-m-d');
        $hora = date('H:i:s');


        $venta = new Venta();
        $venta->setUsuario($usuario);
        $venta->setCliente($cliente);
        $venta->setDescuentos($descuentoall);
        $venta->setTotal($total_venta);
        $venta->setSubTotal($subtotal_venta);
        $venta->setIva($subtotal_iva);
        $venta->setNoFactura($nofactura);
        $venta->setDescuentoFijo($descuentofijo);
        $venta->setCantproductos($item_selected);
        $venta->setFecha($fecha);
        $venta->setHora($hora);

        $em->persist($venta);
        $em->flush();

        $formasPago = $request->get('fp');

        foreach ($formasPago as $fp) {
            $ventaFP = new VentaFormaPago();

            $id_forma_pago = $fp['id_forma_pago'];
            $descuento = $fp['descuento'];
            $recargo = $fp['recargo'];
            $monto = $fp ['monto'];

            $ventaFP->setDescuento($descuento);
            $ventaFP->setFormaPago($em->getRepository('BrioozTaskBundle:FormaPago')->find($id_forma_pago));
            $ventaFP->setMonto($monto);
            $ventaFP->setRecargo($recargo);
            $ventaFP->setVenta($venta);

            $em->persist($ventaFP);
            $em->flush();
        }

        //productos relacionados con la venta
        $productos = $request->get('productos');

        if ($productos != null) {

            foreach ($productos as $prod) {

                $idProducto = $prod['id'];
                $cantidad = $prod['cantidad'];
                $talla = $prod['talla'];

                $productoObj = $em->getRepository('BrioozTaskBundle:Producto')->find($idProducto);

                //actualizar los datos del producto
                //actualizar los valores del stock del producto
                $stockProducto = $productoObj->getTotal();
                $stockProducto = $stockProducto - $cantidad;

                $productoObj->setTotal($stockProducto);
                $em->persist($productoObj);
                $em->flush();

                //buscar los registros de producto talla
                $productoTalla = $em->getRepository('BrioozTaskBundle:ProductoTalla')->findOneBy(array('producto1' => $idProducto, 'talla' => $talla));

                if ($productoTalla != null) {
                    $cantidadTalla = $productoTalla->getCantidad();
                    $cantidadTalla = $cantidadTalla - $cantidad;
                    $productoTalla->setCantidad($cantidadTalla);
                    $em->persist($productoTalla);
                    $em->flush();
                }

                //nuevo registro ventaProducto
                $ventaProducto = new VentaProducto();

                $ventaProducto->setCantidad($cantidad);
                $ventaProducto->setProducto($productoObj);
                $ventaProducto->setVenta($venta);

                $em->persist($ventaProducto);
                $em->flush();
            }
        }


        return new Response(
                json_encode(array('id' => 1)), 200, array('Content-Type' => 'application/json')
        );
    }

    public function deleteAction(Request $request) {
        
    }

    private function createCustomForm($id, $method, $route) {
        return $this->createFormBuilder()
                        ->setAction($this->generateUrl($route, array('id' => $id)))
                        ->setMethod($method)
                        ->getForm();
    }

}
