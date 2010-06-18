<?php
class Controller_Glue extends Controller {
	public function action_sandbox() {
		if (Kohana::config('glue')->debug) {
			$query = isset($_GET['query']) ? $_GET['query'] : null;
			$this->request->response = View::factory('glue_template')
				->set('title', 'Sandbox')
				->set('content',
					View::factory('glue_sandbox')->set('query', $query)
				);
		}
		else
			$this->request->status = 404;
	}
	
	public function action_entity($entity) {
		if (Kohana::config('glue')->debug) {
			$this->request->response = View::factory('glue_template')
				->set('title', ucfirst($entity) . ' entity')
				->set('content', glue::entity($entity)->debug());
		}
		else
			$this->request->status = 404;
	}

	public function action_relationship($entity, $relationship) {
		if (Kohana::config('glue')->debug) {
			$this->request->response = View::factory('glue_template')
				->set('title', ucfirst($entity) . '->' . $relationship . ' relationship')
				->set('content', glue::relationship($entity, $relationship)->debug());
		}
		else
			$this->request->status = 404;
	}

	public function action_media($file) {
		$ext	= pathinfo($file, PATHINFO_EXTENSION);
		$file	= substr($file, 0, -(strlen($ext) + 1));
		if ($file = Kohana::find_file('media', $file, $ext)) {
			$this->request->response = file_get_contents($file);
		}
		else {
			$this->request->status = 404;
		}
		$this->request->headers['Content-Type'] = File::mime_by_ext($ext);
	}
}
?>