<?php
class Controller_OGL extends Controller {
	public function action_sandbox () {
		$this->request->response = View::factory('ogl_sandbox');
	}

	public function action_media($file) {
		// Find the file extension
		$ext = pathinfo($file, PATHINFO_EXTENSION);

		// Remove the extension from the filename
		$file = substr($file, 0, -(strlen($ext) + 1));

		if ($file = Kohana::find_file('media', $file, $ext)) {
			// Send the file content as the response
			$this->request->response = file_get_contents($file);
		}
		else {
			// Return a 404 status
			$this->request->status = 404;
		}

		// Set the content type for this extension
		$this->request->headers['Content-Type'] = File::mime_by_ext($ext);
	}
}
?>
