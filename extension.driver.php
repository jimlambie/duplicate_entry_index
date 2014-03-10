<?php
	
	require_once(TOOLKIT . '/class.sectionmanager.php');
	
	class Extension_Duplicate_Entry_Index extends Extension {
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'AddCustomActions',
					'callback'	=> 'addCustomActions'
				),
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'CustomActions',
					'callback'	=> 'processCustomActions'
				)
			);
		}

		public function addCustomActions($context) {
			$options = $context['options'];
			$index = count($options) + 1;

			$callback = Administration::instance()->getPageCallback();
      $sm = new SectionManager(Administration::instance());

      $current_section = $sm->fetch($sm->fetchIDFromHandle($callback['context']['section_handle']));
      $current_section_hash = $this->serialiseSectionSchema($current_section);

      $duplicate_sections = array();

      foreach($sm->fetch() as $section) {
        $section_hash = $this->serialiseSectionSchema($section);
        if ($section_hash == $current_section_hash && $section->get('handle') != $current_section->get('handle')) {
               $duplicate_sections[$section->get('handle')] = $section->get('name');
        }
      }

			if (count($duplicate_sections) > 0) {
			  $options[$index] = array('label' => __('Save As New'), 'options' => array());
			  foreach($duplicate_sections as $handle => $name) {
			    $options[$index]['options'][] = array('saveas-'.$handle, false, $name);
			  }
			}

			$context['options'] = $options;
		}

		public function processCustomActions($context) {

			$checked = $context['checked'];

			switch($_POST['with-selected']) {

				default:

					list($option, $section_handle) = explode('-', $_POST['with-selected'], 3);

					if ($option == 'saveas') {

						$new_section_id = SectionManager::fetchIDFromHandle($section_handle);
						$new_section = SectionManager::fetch($new_section_id);

						foreach($checked as $entry_id){
							$entry = EntryManager::fetch($entry_id);
							$existing_data = $entry[0]->getData();

							$existing_section = SectionManager::fetch($entry[0]->get('section_id'));
							$existing_schema = $existing_section->fetchFieldsSchema();

							$new_entry = EntryManager::create();
				      $new_entry->set('section_id', $new_section_id);
				      $new_entry->set('author_id', is_null(Symphony::Engine()->Author) ? '1' : Symphony::Engine()->Author->get('id'));
				      $new_entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));
				      $new_entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));							

							foreach($existing_schema as $info){
								$result = null;
								
								$field = FieldManager::fetch($info['id']);
								$field_in_new_section = FieldManager::fetchFieldIDFromElementName($info['element_name'], $new_section_id);

								// get data from existing entry
								$result = $entry[0]->getData($info['id']);

								// set it in the new entry
								$new_entry->setData($field_in_new_section, $result);
							}

							$new_entry->commit();

						}

					 	redirect($_SERVER['REQUEST_URI']);
					}

				break;
			}
		}

		private function serialiseSectionSchema($section) {
			$current_section_fields = $section->fetchFieldsSchema();
			foreach($current_section_fields as $i => $field) {
				unset($current_section_fields[$i]['id']);
				unset($current_section_fields[$i]['location']);
			}
			return md5(serialize($current_section_fields));
		}

	}
	
?>
