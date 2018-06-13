<?php

ini_set('memory_limit', '500M');
gc_enable();

$time_start = microtime(true);

buildChm();

////////////

$time_end = microtime(true);
$time = $time_end - $time_start;
echo( "\nbuild time: ".  $time." seconds.\n");

function buildChm( $cpp = true )
{
	$scriptDir = dirname( __FILE__ );
	$targetDir = $scriptDir.DIRECTORY_SEPARATOR ."chmhelp";

	if ( file_exists( $targetDir ) == false )
	{
		if ( mkdir( $targetDir ) == false )
		{
			echo("can't create output directory: ". $targetDir."\n");
		}
	}

	//for debuging set false to aviod recreation of xhtml files.
	$processFiles = true;

	$sourceDir = $scriptDir.DIRECTORY_SEPARATOR ."zh";
	$commonDir = $scriptDir.DIRECTORY_SEPARATOR ."common";
	$cssDir = $scriptDir.DIRECTORY_SEPARATOR ."css";

	$keywordsFiles = array();
	$errorsFiles = array();
	$treeFiles = array();
	$arrFiles = array();
	dirToArray( $arrFiles, $sourceDir, ".html" );

	asort( $arrFiles );

	echo( "files: ".count( $arrFiles )."\n");

	$processedFiles = 0;
	$noTitleLinks = 0;
	$colspans = 0;

	foreach( $arrFiles as $file )
	{
		$filelow = strtolower( $file );
		$relativename = substr( $filelow, strlen( $sourceDir ) + 1 );
		$relativename = str_replace( "%", "_", $relativename ); // escape %

		// current file
		//echo( $relativename."\n" );

		$targetfile = $targetDir.DIRECTORY_SEPARATOR;
		$targetfile .= str_replace( DIRECTORY_SEPARATOR , "-", $relativename );
		$id = str_replace( ".html", "", $relativename );

		// start processing
		$processedFiles++;

		$id = str_replace( "\\", "/", $id );
		$parent_id = "";
		$patharr = explode( "/", $id );
		if ( count( $patharr ) > 1 )
		{
			array_pop( $patharr );
			foreach( $patharr as $pathelem )
			{
				if ( strlen($parent_id) > 0 )
				{
					$parent_id .= "/".$pathelem;
				}
				else
				{
					$parent_id = $pathelem;
				}
			}
		}

		// document id and parent document id
		$id = str_replace( "/", "-", $id );
		$parent_id = str_replace( "/", "-", $parent_id );


		if ( $processFiles )
		{
			// fix xhtml errors
			exec("tidy -config config.txt -o ".$targetfile." ".$file );
		}

		// load xhtml and fix tags
		$dom = new DomDocument('1.0', 'UTF-8');

		$dom->loadHTMLFile( $targetfile );

		if ( $processFiles )
		{

			// delete scripts
			deleteNodes( $dom );

			// fix links to styles
			$nodes = getNodesByName( $dom, "link");
			foreach( $nodes as $e )
			{
				if( $e->hasAttribute("href"))
				{
					$e->setAttribute("href", basename( $e->getAttribute("href")));
				}

				if( $e->getAttribute("rel") != "stylesheet" )
				{
					$e->parentNode->removeChild( $e );
				}
			}

			// remove divs with internal info > 1mb
			$nodes = getNodesByName( $dom, "div");
			foreach( $nodes as $e )
			{
				if( $e->getAttribute("class") == "printfooter" || strpos( $e->getAttribute("class"), "catlinks" ) !== false || strpos( $e->getAttribute("class"), "coliru-btn" ) !== false )
				{
					$e->parentNode->removeChild( $e );
				}
				else if ( $e->getAttribute('id') == "siteSub" || $e->getAttribute('id') == "mw-js-message")
				{
					$e->parentNode->removeChild( $e );
				}

				// remove navbar?
				if ($e->getAttribute("class") == "t-navbar")
				{
					$e->parentNode->removeChild( $e );
				}
			}


			// fix links to other pages
			$nodes = array_merge( getNodesByName( $dom, "a"), getNodesByName( $dom, "area"));
			foreach( $nodes as $e )
			{
				if( $e->hasAttribute("title"))
				{
					$href = $e->getAttribute("href");
					if ( strpos( $href, "http") === false )
					{
						$new_value = str_replace( "/" , "-", $e->getAttribute("title"));
						// fix bad titles where space is used instead of _
						$new_value = str_replace( " " , "_", $new_value);
						if (! strstr($new_value, "http")) {
							// escape relative %
							$new_value = str_replace( "%" , "_", urlencode($new_value));
						}

						$e->setAttribute("href", $new_value.".html" );

						// remove title from links?
						$e->removeAttribute("title");
					}
				}
				else if( $e->hasAttribute("href"))
				{
					$href = $e->getAttribute("href");
					if ( strpos( $href, "http") === false )
					{
						// bad link
						$noTitleLinks++;
						$relativenamefix = str_replace( DIRECTORY_SEPARATOR, "/", $relativename );
						$relative_folders = explode( "/" , $relativenamefix );
						$unFolders = substr_count( $href , "../" );
						$hrefname = str_replace( "../", "", $href );
						$hrefname = str_replace( ".html", "", $hrefname );

						$unFolders++;
						for ( $ind = 0; $ind < $unFolders; $ind++)
						{
							array_pop( $relative_folders );
						}

						$fref_folders = explode( "/" , $hrefname );

						foreach ($fref_folders as $frf)
						{
							$relative_folders[] = $frf;
						}

						$fixedhref = implode( "-", $relative_folders );
						$e->setAttribute("href", $fixedhref.".html" );
					}
				}
			}

			// remove unwanted colspan="5", about 4mb of text!
			$nodes = getNodesByName( $dom, "td");
			foreach( $nodes as $e )
			{
				if( $e->hasAttribute("colspan") && $e->getAttribute("colspan") == 5 )
				{
					$e->removeAttribute("colspan");
					$colspans++;
				}
			}

			// fix links to images
			$nodes = getNodesByName( $dom, "img");
			foreach( $nodes as $e )
			{
				if( $e->hasAttribute("src"))
				{
					$e->setAttribute("src", basename( $e->getAttribute("src")));
				}
			}
		}

		// getting and fixing document title remplace <h1> to <div class="title">
		$document_title = "";
		$h1title = $dom->getElementById("firstHeading");
		if ( $h1title )
		{
			foreach( $h1title->childNodes as $h1n )
			{
				$document_title .= $h1n->textContent;
			}
		}
		else
		{
			// no page title
			$errorsFiles[] = $targetfile;
		}

		// building keywords array
		$keywords = array();
		$pre_keywords = explode( ",", htmlspecialchars_decode( $document_title ));
		foreach ( $pre_keywords as $keywrd )
		{
			if ( strpos( $keywrd, "std::" ) !== false )
			{
				$post_keywords = explode( "::", $keywrd);
				$keywords[] = $post_keywords[ count( $post_keywords ) - 1 ];

			}
			else if ( strpos( $keywrd, ":" ) !== false )
			{
				$post_keywords = explode( ":", $keywrd);
				$keywords[] = $post_keywords[ count( $post_keywords ) - 1 ];
			}
			else
			{
				$keywords[] = $keywrd;
			}
		}
		if ( count( $keywords ) == 0 )
		{
			$keywords = $pre_keywords;
		}
		foreach ( $keywords as $keywrd )
		{
			$keywordsFiles[trim($keywrd)][] = 'chmhelp\\'.$id.".html";
		}

		// building toc tree
		$id_path = explode("-", $id );
		$last_elem = array_pop( $id_path );
		$file_name = 'chmhelp\\'.$id.".html";
		$current_root = &$treeFiles;
		foreach ( $id_path as $path_obj )
		{
			foreach ( $current_root as $object )
			{
				if ( $object->id == $path_obj )
				{
					$current_root = &$object->childrens;
					break;
				}
			}
		}
		$current_root[] = (object) array('title' => $document_title, 'url' => $file_name, 'id' => $last_elem, 'childrens' => array());

		// current file info
		echo("id: ".$id." parent id: ".$parent_id." title: ".$document_title."\n");

		if ( $processFiles )
		{
			// Save processed file
			$dom->saveHTMLFile( $targetfile );
			exec("tidy -config config.txt -o ".$targetfile." ".$targetfile );
		}

		if ( $processedFiles > 50 )
		{
			//break;
		}
		gc_collect_cycles();
	}
	echo ("total files processed: ".$processedFiles ." errors: ".count($errorsFiles)."\n" );

	// building table of contents
	echo( "building toc cppreference.hhc ...\n");
	echo( "files: ".count( $treeFiles )."\n");
	$metadata = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">'."\n";
	$metadata .= '<html><head><meta name="GENERATOR" content="Script from CEZEO software Ltd."></head><body>'."\n";
	$metadata .= '<object type="text/site properties">';
	$metadata .= "\n\t".'<param name="ImageType" value="Folder">'."\n\t</object>\n";
	$metadata .= "<ul>\n";

	buildTree( $treeFiles, $metadata, 1 );

	$metadata .= "\n</ul></body></html>";
	file_put_contents( $scriptDir.DIRECTORY_SEPARATOR ."cppreference.hhc", $metadata );

	// building keywords index
	echo( "building keywords index cppreference.hhk ...\n");
	$metadata = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">'."\n";
	$metadata .= '<html><head><meta name="GENERATOR" content="Script from CEZEO software Ltd."></head><body>'."\n<ul>\n";

	foreach ( array_keys( $keywordsFiles ) as $kwf )
	{
		$metadata .= "\t<li><object type=\"text/sitemap\">\n";
		$metadata .= "\t\t<param name=\"Name\" value=\"".$kwf."\">\n";
		foreach ($keywordsFiles[$kwf] as $file)
		{
			$metadata .= "\t\t<param name=\"Local\" value=\"".$file."\">\n";
		}
		$metadata .= "\t</object>\n";
	}

	$metadata .= "\n</ul></body></html>";
	file_put_contents( $scriptDir.DIRECTORY_SEPARATOR ."cppreference.hhk", $metadata );

	// building project file
	echo( "building project cppreference.hhp ...\n");
	$metadata = "[OPTIONS]\nCompatibility=1.1 or later\nCompiled file=cppreference.chm\nContents file=cppreference.hhc\nDefault Window=default\nDefault topic=chmhelp\index.html\nDisplay compile progress=No\nFull-text search=Yes\nIndex file=cppreference.hhk\nLanguage=0x804 中文(简体，中国)\nTitle=Title=C / C++ 参考文档\n[WINDOWS]\n";
	$metadata .= 'default="C / C++ 参考文档","cppreference.hhc","cppreference.hhk","chmhelp\index.html","chmhelp\index.html",,,,,173408,250,0,[0,32,1080,700],0x0000000,,,,,0,0'."\n[FILES]\n";

	buildListOfFiles( $treeFiles, $metadata );

	$metadata .= "\n";
	file_put_contents( $scriptDir.DIRECTORY_SEPARATOR ."cppreference.hhp", $metadata );

	// copying images etc.
	$arrFiles = array();
	dirToArray( $arrFiles, $commonDir, "." );
	echo( "copying images and styles ...\n");
	echo( "files: ".count( $arrFiles )."\n");
	foreach( $arrFiles as $file )
	{
		if ( endsWith( $file, '.css' ))
		{
			copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
			echo( "file copied: ".basename( $file )."\n");
		}
		else if ( endsWith( $file, '.png' ))
		{
			copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
			echo( "file copied: ".basename( $file )."\n");
		}
		else if ( endsWith( $file, '.svg' ))
		{
			copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
			echo( "file copied: ".basename( $file )."\n");
		}
		gc_collect_cycles();
	}

	// replace css filse
	$arrFiles = array();
	dirToArray( $arrFiles, $cssDir, ".css" );
	echo( "copying new styles ...\n");
	echo( "files: ".count( $arrFiles )."\n");
	foreach( $arrFiles as $file )
	{
		copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
		echo( "file copied: ".basename( $file )."\n");
		gc_collect_cycles();
	}
}

function buildTree(&$array, &$metadata, $level )
{
	foreach( $array as $object )
	{
		if ( count( $object->childrens ) > 0 )
		{
			insertTabs( $metadata, $level );
			$metadata .= '<li><object type="text/sitemap"><param name="Name" value="'.$object->title.'"><param name="ImageNumber" value="1"></object>'."\n";
			insertTabs( $metadata, $level );
			$metadata .= "<ul>\n";
			$level++;
		}

		insertTabs( $metadata, $level );
		$metadata .= '<li><object type="text/sitemap"><param name="Name" value="'.htmlspecialchars( $object->title ).'"><param name="Local" value="'.$object->url.'"></object>'."\n";

		if ( count( $object->childrens ) > 0 )
		{
			buildTree( $object->childrens, $metadata, ( $level ));
			$level--;
			insertTabs( $metadata, $level );
			$metadata .= "</ul>\n";
		}
	}
}

function insertTabs( &$metadata, $level )
{
	for( $cnt = 0; $cnt < $level; $cnt++)
	{
		$metadata .= "\t";
	}
}

function buildListOfFiles(&$array, &$metadata )
{
	foreach( $array as $object )
	{
		$metadata .= $object->url."\n";
		if ( count( $object->childrens ) > 0 )
		{
			buildListOfFiles( $object->childrens, $metadata );
		}
	}
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0)
    {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function addNewMeta( $dom, $head, $name, $content )
{
	$new_meta = $dom->createElement('meta');
	$new_attr = $dom->createAttribute('name');
	$new_attr->value = $name;
	$new_meta->appendChild($new_attr);
	$new_attr = $dom->createAttribute('content');
	$new_attr->value = $content;
	$new_meta->appendChild($new_attr);
	$head->appendChild($new_meta);
}

function getNodesByName( $nde, $name )
{
	$elems = array();
	if ( $nde->childNodes )
	{
	   	foreach ($nde->childNodes as $nd)
		{
	       	$elems = array_merge( getNodesByName( $nd, $name ), $elems);
	 	}
	}

	if ( $nde->nodeName === $name)
	{
		$elems[] = $nde;
	}
	return $elems;
}

function deleteNodes( $nde )
{
	if ( $nde->childNodes )
	{
		$toRemove = array();
	   	foreach ($nde->childNodes as $nd)
		{
			deleteNodes( $nd );
			if ( deleteNode((string)$nd->nodeName ))
			{
				$toRemove[] = $nd;
			}
		}
		foreach ($toRemove as $remNd)
		{
			$nde->removeChild( $remNd );
		}
	}
}

function deleteNode( $nodeName )
{
	if ( $nodeName == "script" || $nodeName == "meta" || $nodeName == "style" )
	{
		return true;
	}
	return false;
}

function dirToArray(&$files, $dir, $ext)
{
   $cdir = scandir($dir);
   foreach ($cdir as $key => $value)
   {
      if (!in_array($value,array(".","..")))
      {
         if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
         {
            dirToArray($files, $dir . DIRECTORY_SEPARATOR . $value, $ext);
         }
         else
         {
         	if ( strpos( $value, $ext ) !== false )
         	{
         		if ( strpos( $value, "\\" ) === 0 )
         		{
         	 	  $files[] = $dir.$value;
         		}
         		else
         		{
         			$files[] = $dir."\\".$value;
         		}
        	}
         }
      }
   }
}
?>