<?php
/**
 * @version 1.0 $Id: termlist.php 1862 2014-03-07 03:29:42Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.termlist
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.event.plugin');

class plgFlexicontent_fieldsTermlist extends JPlugin
{
	static $field_types = array('termlist');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsTermlist( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_termlist', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		
		// Create the editor object of editor prefered by the user,
		// this will also add the needed JS to the HTML head
		$editor_name = $field->parameters->get( 'editor',  $user->getParam('editor', $app->getCfg('editor'))  );
		$editor  = JFactory::getEditor($editor_name);
		$editor_plg_params = array();  // Override parameters of the editor plugin, ignored by most editors !!
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || $field->parameters->get( 'allow_multiple', 0 ) ;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = (int)$field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$add_position = (int) $field->parameters->get( 'add_position', 3 ) ;
		
		
		// **********
		// Term title
		// **********
		
		// Label
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		
		// Default value
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		// Input field display size & max characters
		$title_size      = $field->parameters->get( 'title_size', 80 ) ;
		$title_maxlength = $field->parameters->get( 'title_size', 0 ) ;
		
		
		// ***********************
		// Term text (description)
		// ***********************
		
		// Label
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		
		// Default value
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($item->version == 0 || $value_usage > 0) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = $default_value ? JText::_($default_value) : '';
		
		// Editing method, text editor or HTML editor
		$use_html  = $field->parameters->get( 'use_html', 1 );
		
		// *** Simple Textarea ***
		$rows  = $field->parameters->get( 'rows', 3 ) ;
		$cols  = $field->parameters->get( 'cols', 80 ) ;
		$maxlength = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced when using textarea, otherwise this will depend on the HTML editor (and only will be client size only)
		
		// *** HTML Editor configuration  ***
		$width = $field->parameters->get( 'width', '98%') ;
		if ($width != (int)$width) $width .= 'px';
		$height = $field->parameters->get( 'height', '250px' ) ;
		if ($height != (int)$height) $height .= 'px';
		
		// Decide editor plugin buttons to SKIP
		$show_buttons = $field->parameters->get( 'show_buttons', 1 ) ;
		$skip_buttons = $field->parameters->get( 'skip_buttons', '' ) ;
		$skip_buttons = is_array($skip_buttons) ? $skip_buttons : explode('|',$skip_buttons);
		
		// Clear empty value
		if (empty($skip_buttons[0]))  unset($skip_buttons[0]);
		
		// Force skipping pagebreak and readmore for CUSTOM textarea fields
		if ($field->field_type == 'textarea') {
			if ( !in_array('pagebreak', $skip_buttons) ) $skip_buttons[] = 'pagebreak';
			if ( !in_array('readmore',  $skip_buttons) )  $skip_buttons[] = 'readmore';
		}
		$skip_buttons_arr = ($show_buttons && $editor_name=='jce' && count($skip_buttons)) ? $skip_buttons : (boolean) $show_buttons;   // JCE supports skipping buttons
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['title'] = $default_title;
			$field->value[0]['text']  = $default_value;
			$field->value[0] = serialize($field->value[0]);
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function addField".$field->id."(el, groupval_box, fieldval_box, params)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				var lastField = fieldval_box ? fieldval_box : jQuery(el).prev().children().last();
				var newField  = lastField.clone();
				
				// Update the new term title
				newField.find('input.termtitle').val('');
				newField.find('input.termtitle').attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
				newField.find('input.termtitle').attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				
				";
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			$js .= "
				// Update the new term description
				var boxClass = 'termtext';
				var container = newField.find('.fc_'+boxClass);
				var editor = typeof tinyMCE === 'undefined' ? false : tinyMCE.get( container.find('textarea').first().attr('id') );
				
				container.after('<div class=\"nowrap_box fc_'+boxClass+'\"></div>');  // Append a new container box
				container.find('label.labeltext').show().appendTo(container.next()); // Copy the label
				container.find('textarea').show().css('visibility', 'visible').appendTo(container.next()); // Copy only the textarea (first make it visible) into the new container
				container.remove(); // Remove old (cloned) container box along with all the contents
				
				// Prepare the new textarea for attaching the HTML editor
				theArea = newField.find('.fc_'+boxClass).find('textarea');
				theArea.val('');
				theArea.attr('name','".$fieldname."['+uniqueRowNum".$field->id."+'][text]');
				theArea.attr('id','".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				theArea.removeClass(); // Remove all classes from the textarea
				
				// Update the labels
				newField.find('label.labeltitle').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_title');
				newField.find('label.labeltext').attr('for', '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
				";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
			
			// Attach a new JS HTML editor object
			if ($use_html) $js .= "
				if (editor)
				{
          if(tinyMCE.majorVersion >= 4) {
          	//var ed = new tinymce.Editor('textareaid', { mode : 'exact' }, tinymce.EditorManager);
						//tinyMCE.init({  mode : 'exact',  elements :'".$elementid."_'+uniqueRowNum".$field->id."+'_text'  });
          	//tinyMCE.editors['".$elementid."_'+uniqueRowNum".$field->id."+'_text'].show();
						tinyMCE.EditorManager.execCommand('mceAddEditor', true, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
          } else {
          	tinyMCE.EditorManager.execCommand('mceAddControl', true, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
						//tinyMCE.execCommand('mceAddControl', true, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
          }
          //tinyMCE.EditorManager.execCommand('mceFocus', false, '".$elementid."_'+uniqueRowNum".$field->id."+'_text');
					theArea.addClass('mce_editable').addClass(boxClass);
					newField.find('.mce-container').css('display', '');
				}
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 1)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 1) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){
						var txtareas = jQuery(this).find('textarea');
						txtareas.each(function( i, txtarea) {
							var areaid = jQuery(txtarea).attr('id');
							var editor = typeof tinyMCE === 'undefined' ? false : tinyMCE.get(areaid);
							if (editor) tinymce.EditorManager.execCommand('mceRemoveEditor', false, areaid);
						});
						this.remove();
					});
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '';
			
			$remove_button = '<span class="fcfield-delvalue" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
			$add_here = '';
			$add_here .= $add_position==2 || $add_position==3 ? '<span class="fcfield-insertvalue fc_before" onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 1});" title="'.JText::_( 'FLEXI_ADD_BEFORE' ).'"></span> ' : '';
			$add_here .= $add_position==1 || $add_position==3 ? '<span class="fcfield-insertvalue fc_after"  onclick="addField'.$field->id.'(null, jQuery(this).closest(\'ul\'), jQuery(this).closest(\'li\'), {insert_before: 0});" title="'.JText::_( 'FLEXI_ADD_AFTER' ).'"></span> ' : '';
		} else {
			$remove_button = '';
			$move2 = '';
			$add_here = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		
		
		// *****************************************
		// Create field's HTML display for item form
		// *****************************************
		
		$field->html = array();
		$n = 0;
		//if ($use_ingroup) {print_r($field->value);}
		foreach ($field->value as $value)
		{
			// Compatibility for unserialized values
			if ( @unserialize($value)!== false || $value === 'b:0;' ) {
				$value = unserialize($value);
			} else {
				$value = array('title' => $value, 'text' => '');
			}
			if ( empty($value['title']) && !$use_ingroup && $n) continue;  // If at least one added, skip empty if not in field group
			
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;
			
			$title = '
				<div class="nowrap_box fc_termtitle">
					<label class="label label-info labeltitle" for="'.$elementid_n.'_title">'.$title_label./*' '.($multiple?($n+1):'').*/'</label>
					<input class="fcfield_textval termtitle '.$required.'" id="'.$elementid_n.'_title" name="'.$fieldname_n.'[title]" type="text" size="'.$title_size.'" maxlength="'.$title_maxlength.'" value="'.htmlspecialchars( @$value['title'], ENT_COMPAT, 'UTF-8' ).'" />
				</div>';
			
			// NOTE: HTML tag id of this form element needs to match the -for- attribute of label HTML tag of this FLEXIcontent field, so that label will be marked invalid when needed
			//display($name, $html, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null, $params = array())
			$text = !$use_html ? '
				<textarea class="fcfield_textval termtext" id="'.$elementid_n.'_text" name="'.$fieldname_n.'[text]" cols="'.$cols.'" rows="'.$rows.'">'
					.htmlspecialchars( $value['text'], ENT_COMPAT, 'UTF-8' ).
				'</textarea>
				' : $editor->display(
						$fieldname_n.'[text]', htmlspecialchars( $value['text'], ENT_COMPAT, 'UTF-8' ), $width='100%', $height='100%', $_cols='', $_rows='',
						$show_buttons, $elementid_n.'_text'
				);
			
			$text = '
				<div class="nowrap_box fc_termtext">
					<label class="label label-info labeltext" for="'.$elementid_n.'_text">'.$value_label.' './*($multiple?($n+1):'').*/'</label>
					'.$text.'
				</div>';
			
			$field->html[] = '
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				'.($use_ingroup || !$add_position ? '' : $add_here).'
				'.($use_ingroup ? '' : '<div class="fcclear"></div>').'
				'.$title.'
				'.$text.'
				';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			if (!$add_position) $field->html .= '<span class="fcfield-addvalue fccleared" onclick="addField'.$field->id.'(this);" title="'.JText::_( 'FLEXI_ADD_TO_BOTTOM' ).'"></span>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// Some variables
		$use_ingroup = !empty($field->ingroup);  //$field->parameters->get('use_ingroup', 0);
		$view = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		
		// Value handling parameters
		$lang_filter_values = 0;//$field->parameters->get( 'lang_filter_values', 1);
		$clean_output = $field->parameters->get('clean_output', 0);
		$encode_output = $field->parameters->get('encode_output', 0);
		$multiple = $use_ingroup || $field->parameters->get( 'allow_multiple', 0 ) ;
		
		// Term Title
		$title_label = JText::_($field->parameters->get('title_label', 'FLEXI_FIELD_TERMTITLE'));
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2) ? JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		$default_title = $default_title ? JText::_($default_title) : '';
		
		// Term (description) Text
		$value_label = JText::_($field->parameters->get('value_label', 'FLEXI_FIELD_TERMTEXT'));
		$value_usage   = $field->parameters->get( 'default_value_use', 0 ) ;
		$default_value = ($value_usage == 2) ? $field->parameters->get( 'default_value', '' ) : '';
		$default_value = $default_value ? JText::_($default_value) : '';
		
		// Get field values
		$values = $values ? $values : $field->value;
		
		// Load default value
		if ( empty($values) ) {
			if (!strlen($default_value)) {
				$field->{$prop} = $use_ingroup ? array() : '';
				return;
			}
			$values = array();
			$values[0]['title'] = $default_title;
			$values[0]['text']  = $default_value;
			$values[0] = serialize($values[0]);
		}
		
		
		// ******************************************
		// Language filter, clean output, encode HTML
		// ******************************************
		
		if ($clean_output) {
			$ifilter = $clean_output == 1 ? JFilterInput::getInstance(null, null, 1, 1) : JFilterInput::getInstance();
		}
		if (1)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for unserialized values or for NULL values in a field group
				$v = !empty($value) ? @unserialize($value) : false;
				if ( $v !== false || $v === 'b:0;' ) {
					$value = $v;
				} else {
					$value = array('title' => $value, 'text' => '');
				}
				
				if ($lang_filter_values) {
					$value['title'] = JText::_($value['title']);
					$value['text']  = JText::_($value['text']);
				}
				if ($clean_output) {
					$value['title'] = $ifilter->clean($value['title'], 'string');
					$value['text']  = $ifilter->clean($value['text'], 'string');
				}
				if ($encode_output) {
					$value['title'] = htmlspecialchars( $value['title'], ENT_QUOTES, 'UTF-8' );
					$value['text']  = htmlspecialchars( $value['text'], ENT_QUOTES, 'UTF-8' );
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}
		
		
		// Prefix - Suffix - Separator parameters, replacing other field values if found
		$remove_space = $field->parameters->get( 'remove_space', 0 ) ;
		$pretext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'pretext', '' ), 'pretext' );
		$posttext		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'posttext', '' ), 'posttext' );
		$separatorf	= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'opentag', '' ), 'opentag' );
		$closetag		= FlexicontentFields::replaceFieldValue( $field, $item, $field->parameters->get( 'closetag', '' ), 'closetag' );
		
		if($pretext)  { $pretext  = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) { $posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			case 5:
			$separatorf = '';
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		// Create field's HTML
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( !strlen($value['title']) && !$use_ingroup ) continue;
			if ( !strlen($value['title']) ) {
				$field->{$prop}[$n++]	= '';
				continue;
			}
			
			$html = '
				<label class="fc_termtitle label label-success">'.$value['title'].'</label>
				<div class="fc_termdesc">'.$value['text'].'</div>';
			
			// Add prefix / suffix
			$field->{$prop}[$n]	= $pretext . $html . $posttext;
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if (!$use_ingroup)  // do not convert the array to string if field is in a group
		{
			// Apply separator and open/close tags
			$field->{$prop} = implode($separatorf, $field->{$prop});
			if ( $field->{$prop}!=='' ) {
				$field->{$prop} = $opentag . $field->{$prop} . $closetag;
			} else {
				$field->{$prop} = '';
			}
		}
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$use_ingroup = $field->parameters->get('use_ingroup', 0);
		if ( !is_array($post) && !strlen($post) && !$use_ingroup ) return;
		
		$is_importcsv = JRequest::getVar('task') == 'importcsv';
		
		// Server side validation
		$validation = $field->parameters->get( 'validation', 2 ) ;
		$use_html   = $field->parameters->get( 'use_html', 0 );
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;
		$maxlength  = $use_html ? 0 : $maxlength;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		// Reformat the posted data
		$newpost = array();
		$new = 0;
		foreach ($post as $n => $v)
		{
			// support for basic CSV import / export
			if ( $is_importcsv && !is_array($post[$n]) ) {
				if ( @unserialize($post[$n])!== false || $post[$n] === 'b:0;' ) {  // support for exported serialized data)
					$post[$n] = unserialize($post[$n]);
				} else {
					$post[$n] = array('title' => $post[$n], 'text' => '');
				}
			}
			
			
			// **************************************************************
			// Validate data, skipping values that are empty after validation
			// **************************************************************
			
			$title = flexicontent_html::dataFilter($post[$n]['title'], $maxlength, 'HTML', 0);
			if (!strlen($title) && !$use_ingroup) continue; // Skip empty values
			
			$newpost[$new] = array();
			$newpost[$new]['title'] = $title;
			$newpost[$new]['text']  = flexicontent_html::dataFilter($post[$n]['text'], $maxlength, $validation, 0);
			
			$new++;
		}
		$post = $newpost;
		
		// Serialize multi-property data before storing them into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
		/*if ($use_ingroup) {
			$app = JFactory::getApplication();
			$app->enqueueMessage( print_r($post, true), 'warning');
		}*/
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	
	// *********************************
	// CATEGORY/SEARCH FILTERING METHODS
	// *********************************
	
	// Method to display a search filter for the advanced search view
	function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, self::$field_types) ) return;
		
		$filter->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		FlexicontentFields::createFilter($filter, $value, $formName);
	}
	
	
 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	function getFilteredSearch(&$field, $value)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->parameters->set( 'display_filter_as_s', 1 );  // Only supports a basic filter of single text search input
		return FlexicontentFields::getFilteredSearch($field, $value, $return_sql=true);
	}
	
	
	
	// *************************
	// SEARCH / INDEXING METHODS
	// *************************
	
	// Method to create (insert) advanced search index DB records for the field values
	function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		
		// a. Each of the values of $values array will be added to the advanced search index as searchable text (column value)
		// b. Each of the indexes of $values will be added to the column 'value_id',
		//    and it is meant for fields that we want to be filterable via a drop-down select
		// c. If $values is null then only the column 'value' will be added to the search index after retrieving 
		//    the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexAdvSearch($field, $post, $item, $required_properties=array('title'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
	
	// Method to create basic search index (added as the property field->search)
	function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if ( !$field->issearch ) return;
		
		// a. Each of the values of $values array will be added to the basic search index (one record per item)
		// b. If $values is null then the column value from table 'flexicontent_fields_item_relations' for current field / item pair will be used
		// 'required_properties' is meant for multi-property fields, do not add to search index if any of these is empty
		// 'search_properties'   contains property fields that should be added as text
		// 'properties_spacer'  is the spacer for the 'search_properties' text
		// 'filter_func' is the filtering function to apply to the final text
		FlexicontentFields::onIndexSearch($field, $post, $item, $required_properties=array('title'), $search_properties=array('title','text'), $properties_spacer=' ', $filter_func='strip_tags');
		return true;
	}
	
}
