<?php

	/*------------------------------------------------------------------------
	# aixeenaseometas.php - Aixeena SEO Metas (plugin)
	# ------------------------------------------------------------------------
	# version		1.0.0
	# author    	Ciro Artigot for Aixeena.org
	# copyright 	Copyright (c) 2018 CiroArtigot. All rights reserved.
	# @license 		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
	# Websites 		http://aixeena.org/
	-------------------------------------------------------------------------
	*/
	
	// no direct access
	defined('_JEXEC') or die('Restricted access');

	jimport('joomla.plugin.plugin');

	class plgSystemAixeenaSeoMetas extends JPlugin {
	
	function onContentPrepareForm($form, $data) {
	
		if (!($form instanceof JForm)){
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}
		
		$return = 1;
		if (in_array($form->getName(), array('com_menus.item'))) $return = 0;
		if (in_array($form->getName(), array('com_content.article'))) $return = 0;
		if ($return) return true;	
		
		JForm::addFormPath(dirname(__FILE__) . '/forms');
		if (in_array($form->getName(), array('com_menus.item')) && $this->params->get('articletab',1))  $form->loadFile('menu', false);
		if (in_array($form->getName(), array('com_content.article')) && $this->params->get('menutab',1))  $form->loadFile('article', false);
		return true;
	}
	
	// ........................................................................................**** onBeforeCompileHead()
	function onBeforeCompileHead() {  
	
		$app	= JFactory::getApplication();
		if ($app->isAdmin()) return;
		$doc = JFactory::getDocument();
		$uri 	= JFactory::getURI();
		$config = JFactory::getConfig();
		$jinput = JFactory::getApplication()->input;
		$menu = $app->getMenu();
		$menuactive = $menu->getActive();
		$view = $jinput->get('view','');
		$option = $jinput->get('option','');
		$id = (int) $jinput->get('id',0);
		$cardcreator = $this->params->get('cardcreator','@aixeena'); // card creator
		
		// init variables
		$metadescription = ''; //description
		$imageredes = ''; //image
		$title = ''; //image
		$dublin = 1 ; //dublin metas
		
		$isarticle = 0; // is it a com_content article?
		if($view=='article' && $option=='com_content') $isarticle = 1;
		
		$secure = 0; // is the site SSL Secure?
		if(strpos('https',JURI::base()===true)) $secure = 1;
		
		$menuprimero = 1;
		// When is an article with a menu itemid, the menu params are the first
		if($isarticle && $menuactive->query['view'] == 'article') $articulo_menu = 1;
		// It is an article and is not an own menu itemid the menu params will be ignored
		if($isarticle && $menuactive->query['view'] != 'article') $menuprimero = 0;
		
		// MENU PARAMS if the menu is active and it's not an article (without itemid)
		if(isset($menuactive) && $menuprimero) {
			if($menuactive->params['metadescription'] && $metadescription == '') $metadescription = $menuactive->params['metadescription'];
			if($menuactive->params['menu-meta_description'] && $metadescription == '') $metadescription = $menuactive->params['menu-meta_description'];
			if($menuactive->params['imageredes'] && $imageredes == '') $imageredes = $menuactive->params['imageredes'];
			if($menuactive->params['titulo'] && $title == '') $title = $menuactive->params['titulo'];
		}
		
		// if the page is an article and the description / image / title is empty we need to look in the table
		if($isarticle && ($metadescription == '' || $imageredes == '' || $title == '')) {
			
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('`images`, `attribs`, `introtext`, `fulltext`');
			$query->from('#__content');	
			$query->where('id='.$id);	
			$db->setQuery($query);
			$article = $db->loadObject(); //get the article	
			
			if($article) {
				
				// First the Aixeena SEO params
				$attrb = json_decode($article->attribs); 
				if(isset($attrb->imageredes) && $attrb->imageredes && $imageredes == '') $imageredes = $attrb->imageredes;
				if(isset($attrb->title) && $attrb->title && $title == '') $title = $attrb->title;
				if(isset($attrb->metadescription) && $attrb->metadescription && $metadescription == '') $metadescriptionn = $attrb->metadescription;
				
				// If there is not an image find 1º fulltext or 2º introtext image.
				if($imageredes == '') { 
					$images  = json_decode($article->images);
					if (isset($images->image_fulltext) && !empty($images->image_fulltext)) $imageredes = $images->image_fulltext;
					if ($imageredes == '' && isset($images->image_intro) && !empty($images->image_intro)) $imageredes = $images->image_intro;
				}	
			
				// If there is not an image find text images on intro and fulltext..
				if($imageredes=='') {
					preg_match_all('/<img[^>]+>/i',$article->introtext . $article->fulltext, $imagesintro); 
					if($imagesintro) {
						$img = '';
						$sw = 0;
						foreach($imagesintro[0] as $image) {
							if(strpos($image,'://') === false)  {
								$sw ++;	
								$imagenes_texto = new DOMDocument();
								$imagenes_texto->loadHTML($image);
								
								$tags = $imagenes_texto->getElementsByTagName('img');
								foreach ($tags as $tag) {
									if($sw==1) {
										$img = '/'.$tag->getAttribute('src');
										$imgpath = JPATH_SITE.$img ;
										list($width, $height) = getimagesize($imgpath);					   
									}
								}	
							}
						}
					}
					if($img) $imageredes = $img;
				}
				
				// If there is not metadescription find 160 characters form introtext
				if($article->introtext && $metadescription == '') $metadescription = substr(strip_tags($article->introtext),0,160).'...';
			}
		}
		
		// if the article is not complete look on the menu params
		if(isset($menuactive) && !$menuprimero && ($metadescription == '' || $imageredes == '' || $title == '')) {
			if($menuactive->params['menu-meta_description'] && $metadescription == '') $metadescription = $menuactive->params['menu-meta_description'];
			if($menuactive->params['imageredes'] && $imageredes == '') $imageredes = $menuactive->params['imageredes'];
			if($menuactive->params['titulo'] && $title == '') $title = $menuactive->params['titulo'];
		}
		
		// Default Aixeena SEO image if there is not image
		if($this->params->get('imageredes','') && $imageredes =='') $imageredes = $this->params->get('imageredes','');	
		
		// Get Joomla default metadescription if it's empty
		if($doc->getMetaData('description') && $metadescription == '') $metadescription = $doc->getMetaData('description'); 
		
		$metadescription = strip_tags($metadescription);
	 	$metadescription = preg_replace('/\r\n+|\r+|\n+|\t+/i', '', $metadescription);
		$metadescription = trim(preg_replace('/\t+/', '', $metadescription));
		$metadescription = preg_replace('/\s+/', ' ', $metadescription);
		
		// If there is a SEO title, set it into the headers.
		if($title) $doc->setTitle($title);
	
		// Twitter Cards
		if($this->params->get('twitter',1)) { // twitter cards
			$doc->addCustomTag('<meta name="twitter:card" content="'.$this->params->get('cardtype','summary_large_image').'">');
			$doc->addCustomTag('<meta name="twitter:site" content="'.$this->params->get('cardsite','@aixeena').'">');
			$doc->addCustomTag('<meta name="twitter:creator" content="'.$cardcreator.'">');
			$doc->addCustomTag('<meta name="twitter:title" content="'.htmlspecialchars($doc->getTitle()).'">');
			if($metadescription) $doc->addCustomTag('<meta name="twitter:description" content="'.htmlspecialchars($metadescription).'">');	
			if($imageredes) $doc->addCustomTag('<meta name="twitter:image:src" content="'.JURI::base().$imageredes.'">');
		}

		if($this->params->get('facebook',1)) { // Open graph
			
			if($imageredes) {
				list($width, $height) = getimagesize(JPATH_SITE.'/'.$imageredes);	
				$doc->addCustomTag('<meta property="og:image" content="'.JURI::base().$imageredes.'">');
				if($secure) $doc->addCustomTag('<meta property="og:image:secure_url" content="'.JURI::base().$imageredes.'">');
				$doc->addCustomTag('<meta property="og:image:width" content="'.$width.'">');
				$doc->addCustomTag('<meta property="og:image:height" content="'.$height.'">');
			}
			
			if($metadescription)  $doc->addCustomTag('<meta property="og:description" content="'.htmlspecialchars($metadescription).'">');
			$doc->addCustomTag('<meta property="og:title" content="'.htmlspecialchars($doc->getTitle()).'">');
			$doc->addCustomTag('<meta property="og:url" content="'.$uri->toString().'">');
			if($view =='article') $doc->addCustomTag('<meta property="og:type" content="'.$this->params->get('fbtype','article').'" />');
			$doc->addCustomTag('<meta property="og:site_name" content="'.htmlspecialchars($config->get( 'sitename' )).'">');
			$doc->addCustomTag('<meta property="fb:app_id" content="'.$this->params->get('fbappid','453271191693562').'">');			
		}
		
		// insert canonical link
		if($this->params->get('canonical',1)) $doc->addHeadLink( $uri->toString(), 'canonical', 'rel');
		
		//Experimental DC
		if($this->params->get('dublin',0)  && $dublin) {
			$doc->setMetadata('DC.title', htmlspecialchars($doc->getTitle()));
			if($metadescription)  $doc->setMetadata('DC.description', htmlspecialchars($metadescription));
			$doc->setMetadata('DC.title', htmlspecialchars($doc->getTitle()));
		}	

		return true;
	}
		
}
?>