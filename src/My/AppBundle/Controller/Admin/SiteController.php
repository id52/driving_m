<?php

namespace My\AppBundle\Controller\Admin;

use My\AppBundle\Entity\Image;
use My\AppBundle\Form\Type\ImageFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Yaml\Yaml;

class SiteController extends AbstractEntityController
{
    protected $listFields = array('region', 'title');
    protected $perms = array('ROLE_MOD_CONTENT');
    protected $tmplItem = 'Site/item.html.twig';
    protected $tmplSettings = 'Site/settings.html.twig';

    public function mapsAction(Request $request)
    {
        $form_factory = $this->container->get('form.factory');

        /** @var $fb \Symfony\Component\Form\FormBuilder */
        $fb = $form_factory->createBuilder('form', array(), array('csrf_protection' => false));
        $fb->add('region', 'entity', array(
            'class' => 'AppBundle:Region',
            'label' => 'Регион'
        ));
        $fb->setMethod('get');

        if (!isset($request->get('form')['region'])) {
            $fb->add('submit', 'submit', array('label' => 'admin.buttons.select'));
            $form = $fb->getForm();

            return $this->render('AppBundle:Admin:Site/maps.html.twig', array(
                'form' => $form->createView(),
            ));
        }

        $region_id = $request->get('form')['region'];

        $region = $this->em->find('AppBundle:Region', $region_id);

        $fb->setData(array(
            'region' => $region,
        ));
        $form_region = $fb->getForm();

        $form_region->handleRequest($request);

        $dir_name = '/uploads/images/';
        $file_name_map = 'site_map.'.$region_id.'.png';

        $form_factory = $this->container->get('form.factory');
        /** @var $fb \Symfony\Component\Form\FormBuilder */
        $fb = $form_factory->createNamedBuilder('site', 'form', null, array(
            'translation_domain' => 'site',
        ));
        $fb->add('map_image', 'liip_imagine_image', array(
            'required'     => false,
            'image_filter' => 'no_filter',
            'image_path'   => $dir_name.$file_name_map,
            'constraints'  => array(new Assert\Image(array('mimeTypes' => array('image/png')))),
        ));
        $fb->add('submit', 'submit', array('label' => 'admin.buttons.save', 'translation_domain' => 'messages'));
        $form = $fb->getForm();

        $form->handleRequest($request);
        if ($form->isValid()) {
            /** @var $image \Symfony\Component\HttpFoundation\File\UploadedFile */
            $image = $form->get('map_image')->getData();

            $root_dir = $this->get('kernel')->getRootDir();
            $web_dir = $root_dir.'/../web';
            if ($image) {
                $file = $web_dir.$dir_name.$file_name_map;
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $config = Yaml::parse($root_dir.'/config/config.yml');
            $filter_sets = array_keys($config['liip_imagine']['filter_sets']);
            $imagine_cache_dir = $web_dir.$config['liip_imagine']['cache_prefix'];
            foreach ($filter_sets as $filter) {
                if ($image) {
                    $file = $imagine_cache_dir.'/'.$filter.'/'.$dir_name.$file_name_map;
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }

            if ($image) {
                $image->move($this->get('kernel')->getRootDir().'/../web'.$dir_name, $file_name_map);
            }

            $this->get('session')->getFlashBag()->add('info', 'Обновлено!');

            $this->redirect($this->generateUrl('admin_sites_maps'));
        }

        return $this->render('AppBundle:Admin:Site/maps.html.twig', array(
            'form'        => $form->createView(),
            'form_region' => $form_region->createView(),
        ));
    }

    public function itemAction(Request $request)
    {
        $id = null;
        if ($id = $request->get('id')) {
            $entity = $this->repo->find($id);
            if (!$entity) {
                throw $this->createNotFoundException($this->entityName.' for id "'.$id.'" not found.');
            }
        } else {
            $entity = new $this->entityClassName();
        }

        $coords = (array)$request->get('site_coords');
        if ($coords) {
            $entity->setCoords(array());
        }
        $entity->setCoords((array)$entity->getCoords() + array(
            'x' => isset($coords['x']) ? intval($coords['x']) : 0,
            'y' => isset($coords['y']) ? intval($coords['y']) : 0,
        ));

        $form = $this->createForm(new $this->formClassName(), $entity, array(
            'translation_domain' => $this->entityNameS,
        ));
        $form->handleRequest($request);
        if ($request->isMethod('post')) {
            $image_id = intval($form->get('image_id')->getData());
            $image = $this->em->getRepository('AppBundle:Image')->find($image_id);

            if ($form->isValid()) {
                $this->em->persist($entity);

                if ($entity->getImage()) {
                    $entity->getImage()->setSite(null);
                }
                if ($image) {
                    $image->setSite($entity);
                }

                $this->em->flush();

                if ($id) {
                    $this->get('session')->getFlashBag()->add('success', 'success_edited');
                    return $this->redirect($this->generateUrl($this->routerList));
                } else {
                    $this->get('session')->getFlashBag()->add('success', 'success_added');
                    return $this->redirect($this->generateUrl($this->routerItemAdd));
                }
            }
        }

        return $this->render('AppBundle:Admin:'.$this->tmplItem, array(
            'form'      => $form->createView(),
            'entity'    => $entity,
            'imageForm' => $this->createForm(new ImageFormType(), new Image())->createView(),
        ));
    }

    /**
     * @param $fb FormBuilderInterface
     * @return FormBuilderInterface
     */
    protected function addSettingsFb(FormBuilderInterface $fb)
    {
        $fb->add('opacity_not_active_sites', 'integer');
        return $fb;
    }

    /**
     * @param $data array
     * @param $form FormInterface
     */
    protected function checkData(&$data, FormInterface &$form)
    {
        $data['opacity_not_active_sites'] = max(0, $data['opacity_not_active_sites']);
        $data['opacity_not_active_sites'] = min(100, $data['opacity_not_active_sites']);
    }
}