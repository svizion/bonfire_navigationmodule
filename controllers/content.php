<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 *  Navigation Admin Controller
 *
 *  This is the Navigation Admin Controller, originally developed by Sean Downey.
 *
 *  This work is licensed under the Creative Commons Attribution 3.0 Unported
 *  License. To view a copy of this license, visit
 *  http://creativecommons.org/licenses/by/3.0/ or send a letter to Creative
 *  Commons, 444 Castro Street, Suite 900, Mountain View, California, 94041, USA.
 *
 *  @category   controllers
 *  @subpackage navigation
 *  @package    Bonfire
 *  @author     Shawn Crigger <support@s-vizion.com>
 *  @author     Sean Downey
 *  @copyright  2012 S-Vizion Software and Developments
 *  @license    http://creativecommons.org/licenses/by/3.0/  CCA3L
 *  @version    1.0.0 (last revision: ?, 2011)
 *
 *  @property CI_Loader          $loader
 *  @property CI_Form_validation $form_validation
 *  @property navigation_model $navigation_model
 *  @property navigation_group_model $navigation_group_model
 *
 */
class Content extends Admin_Controller {

	/**
	 * Class Constructor method, checks auth levels, loads cool stuff, setup's controller.
	 *
	 */
	function __construct()
	{
 		parent::__construct();

		$this->auth->restrict('Navigation.Content.View');
		$this->load->model('navigation_model');
		$this->load->model('navigation_group_model');
		$this->lang->load('navigation');
		
//		Assets::add_css('flick/jquery-ui-1.8.13.custom.css');
		Assets::add_js('jquery-ui-1.8.16.custom.min.js');
		
		Template::set_block('sub_nav', 'content/_sub_nav');
	}
	
	
	/** 
	 * function index
	 *
	 * list form data
	 */
	public function index()
	{

		Template::set('groups', $this->navigation_group_model->find_all() );

		$offset = $this->uri->segment(5);

		// Do we have any actions?
		if ($this->input->post('submit'))
		{
			$action = $this->input->post('submit');

			$checked = $this->input->post('checked');

			switch(strtolower($action))
			{
				case 'delete':
					$this->delete($checked);
					break;
			}
		}

		$where = array();

		// Filters
		$filter = $this->input->get('filter');
		switch($filter)
		{
			case 'group':
				$where['navigation.nav_group_id'] = (int)$this->input->get('group_id');
				$this->navigation_model->where('nav_group_id',(int)$this->input->get('group_id'));
				break;
			default:
				break;
		}

		$this->load->helper('ui/ui');

		$this->navigation_model->limit($this->limit, $offset)->where($where);
		$this->navigation_model->select('*');

		Template::set('records', $this->navigation_model->order_by('nav_group_id, position')->find_all());

		// Pagination
		$this->load->library('pagination');

		$this->navigation_model->where($where);
		$total_records = $this->navigation_model->count_all();
		Template::set('total_records', $total_records);

		$this->pager['base_url'] = site_url(SITE_AREA .'/content/navigation/index');
		$this->pager['total_rows'] = $total_records;
		$this->pager['per_page'] = $this->limit;
		$this->pager['uri_segment']	= 5;

		$this->pagination->initialize($this->pager);



		Assets::add_js( $this->load->view('navigation/content/js', null, TRUE), 'inline' );

		Template::set('parents', $this->navigation_model->format_dropdown('title') );
		Template::set('current_url', current_url());
		Template::set('filter', $filter);
		Template::set_view('navigation/content/index');

		Template::set('toolbar_title', lang('navigation_manage'));
		Template::render();
	}
	
	//--------------------------------------------------------------------
	
	
	public function create() 
	{
		$this->auth->restrict('Navigation.Content.Create');

		if ($this->input->post('submit'))
		{
			if ($this->save_navigation())
			{
				Template::set_message(lang('navigation_create_success'), 'success');
				Template::redirect(SITE_AREA.'/content/navigation');
			}
			else
			{
				Template::set_message(lang('navigation_create_failure') . $this->navigation_model->error, 'error');
			}
		}

		Template::set('groups', $this->navigation_group_model->format_dropdown('title') );
		Template::set('parents', $this->navigation_model->order_by('nav_group_id, position')->format_dropdown('title') );

		Template::set_view('content/form');
		Template::set('toolbar_title', lang('navigation_create_new_button'));
		Template::render();
	}

	//--------------------------------------------------------------------

	public function edit() 
	{
		$this->auth->restrict('Navigation.Content.Edit');

		$id = (int)$this->uri->segment(5);
		
		if (empty($id))
		{
			Template::set_message(lang('navigation_invalid_id'), 'error');
			Template::redirect(SITE_AREA.'/content/navigation');
		}

		if ($this->input->post('submit'))
		{
			if ($this->save_navigation('update', $id))
			{
				Template::set_message(lang('navigation_edit_success'), 'success');
				Template::redirect(SITE_AREA.'/content/navigation');
			}
			else 
			{
				Template::set_message(lang('navigation_edit_failure') . $this->navigation_model->error, 'error');
			}
		}

		Template::set('groups', $this->navigation_group_model->format_dropdown('title') );
		Template::set('parents', $this->navigation_model->order_by('nav_group_id, position')->format_dropdown('title') );
		Template::set('navigation', $this->navigation_model->find($id) );

		Template::set('toolbar_title', lang('navigation_edit_heading'));
		Template::set_view('content/form');
		Template::render();		
	}

	//--------------------------------------------------------------------

	public function delete($navs)
	{

		if (empty($navs))
		{
			$nav_id = $this->uri->segment(5);

			if(!empty($nav_id))
			{
				$navs = array($nav_id);
			}
		}

		if (!empty($navs))
		{
			$this->auth->restrict('Navigation.Content.Delete');

			foreach ($navs as $nav_id)
			{
				$nav = $this->navigation_model->find($nav_id);

				if (isset($nav))
				{
					$this->navigation_model->update_parent($nav_id, 0);
					$this->navigation_model->un_parent_kids($nav_id);

					if ($this->navigation_model->delete($nav_id))
					{
						Template::set_message(lang('navigation_delete_success'), 'success');
					}
					  else
					{
						Template::set_message(lang('navigation_delete_failure'). $this->navigation_model->error, 'error');
					}
				}
				else
				{
					Template::set_message(lang('navigation_not_found'), 'error');

				}
			}
		}
		else
		{
			Template::set_message(lang('navigation_empty_list'), 'error');
		}

		redirect(SITE_AREA .'/content/navigation');
	}

	//--------------------------------------------------------------------

	public function save_navigation($type='insert', $id=0) 
	{	
		if ($type == 'insert')
		{
			$_POST['has_kids'] = 0;
			$_POST['position'] = 99;
		}

		$this->form_validation->set_rules('title','Title','required|trim|xss_clean|max_length[30]');			
		$this->form_validation->set_rules('url','URL','required|trim|xss_clean|max_length[150]');			
		$this->form_validation->set_rules('nav_group_id','Group','required|trim|xss_clean|is_numeric|max_length[11]');			
		$this->form_validation->set_rules('parent_id','Parent','required|trim|xss_clean|is_numeric|max_length[11]');			
		if ($this->form_validation->run() === false)
		{
			return false;
		}


		if ($type == 'insert')
		{
			$id = $this->navigation_model->insert($_POST);
			
			if (is_numeric($id))
			{
				$this->navigation_model->update_parent($id, $this->input->post('parent_id'));
				$return = true;
			}
			else
			{
				$return = false;
			}
		}
		else if ($type == 'update')
		{
			// check if there is a parent
			$this->navigation_model->update_parent($id, $this->input->post('parent_id'));
			$return = $this->navigation_model->update($id, $_POST);
		}
		
//		if ($this->input->post('parent_id') != 0) {
//			// there is a parent so update it to set the has_kids field
//			$data = array('has_kids' => 1);
//			$parent_updated = $this->navigation_model->update($this->input->post('parent_id'), $data);
//
//		}
		return $return;
	}

	//--------------------------------------------------------------------

	public function ajax_update_positions()
	{
		dump ( $_POST );
		// Create an array containing the IDs
		$ids = explode(',', $this->input->post('order'));

		// Counter variable
		$pos = 1;

		foreach($ids as $id)
		{
			// Update the position
			$data['position'] = $pos;
			$this->navigation_model->update($id, $data);
			++$pos;
		}
	}
}
