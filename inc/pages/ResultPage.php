<?php
/**
 * "Result" page.
 *
 * @author Timo Tijhof, 2012
 * @since 1.0.0
 * @package TestSwarm
 */

class ResultPage extends Page {

	protected $foundEmpty = false;

	public function execute() {
		// Handle 'raw' output
		$request = $this->getContext()->getRequest();

		$resultsID = $request->getInt( 'item' );
		$isRaw = $request->getBool( 'raw' );

		if ( $resultsID && $isRaw ) {
			$this->serveRawResults( $resultsID );
			exit;
		}

		// Regular request
		$action = ResultAction::newFromContext( $this->getContext() );
		$action->doAction();

		$this->setAction( $action );
		$this->content = $this->initContent();
	}

	protected function initContent() {
		$request = $this->getContext()->getRequest();
		$resultsID = $request->getInt( 'item' );

		$this->setTitle( 'Run result' );
		$this->setRobots( 'noindex,nofollow' );
		$this->bodyScripts[] = swarmpath( 'js/result.js' );

		$error = $this->getAction()->getError();
		$data = $this->getAction()->getData();
		$html = '';

		if ( $error ) {
			$html .= html_tag( 'div', array( 'class' => 'alert alert-error' ), $error['info'] );
			return $html;
		}

		$this->setSubTitle( '#' . $data['resultInfo']['id'] );


		if ( $data['job'] ) {
			$html = '<p><em>'
				. html_tag_open( 'a', array( 'href' => $data['job']['url'], 'title' => 'Back to Job #' . $data['job']['id'] ) ) . '&laquo Back to Job #' . $data['job']['id'] . '</a>'
				. '</em></p>';
		} else {
			$html = '<p><em>Run #' . $data['resultInfo']['runID'] . ' has been deleted. Job info unavailable.</em></p>';
		}

		if ( $data['otherRuns'] ) {
			$html .= '<table class="table table-bordered swarm-results"><thead>'
				. JobPage::getUaHtmlHeader( $data['otherRuns']['userAgents'] )
				. '</thead><tbody>'
				. JobPage::getUaRunsHtmlRows( $data['otherRuns']['runs'], $data['otherRuns']['userAgents'] )
				. '</tbody></table>';
		}

		$html .= '<h3>Information</h3>'
			. '<table class="table table-striped">'
			. '<colgroup><col class="span2"/><col/></colgroup>'
			. '<tbody>'
			. '<tr><th>Run</th><td>'
				. ($data['job']
					? html_tag( 'a', array( 'href' => $data['job']['url'] ), 'Job #' . $data['job']['id'] ) . ' / '
					: ''
				)
				. 'Run #' . htmlspecialchars( $data['resultInfo']['runID'] )
			. '</td></tr>'
			. '<tr><th>Client</th><td>'
				. html_tag( 'a', array( 'href' => $data['client']['userUrl'] ), $data['client']['userName'] )
				. ' / Client #' . htmlspecialchars( $data['resultInfo']['clientID'] )
			. '</td></tr>'
			. ( $data['client']['deviceName'] !== null
				? '<tr><th>Device name</th><td>'
					. $data['client']['deviceName']
					. ' / '
					. html_tag( 'a', array( 'target' => '_blank', 'href' => 'http://wiki.blinkbox.local/wiki/index.php?profile=default&search=' . htmlspecialchars( $data['client']['deviceName'] ) ), 'search for ' . $data['client']['deviceName'] . ' on blinkbox wiki' )
				. '</td></tr>'
				: ''
			)
			. '<tr><th>User-Agent</th><td>'
				. '<code>' . htmlspecialchars( $data['client']['uaID'] ) . '</code><br/>'
				. 'Raw: <br><code>' . htmlspecialchars( $data['client']['userAgent'] ) . '</code><br/>'
				. html_tag( 'a', array( 'target' => '_blank', 'href' => 'http://wiki.blinkbox.local/wiki/index.php?profile=default&search=' . htmlspecialchars( $data['client']['userAgent'] ) ), 'search for this user agent on blinkbox wiki' )
			. '</td></tr>'
			. '<tr><th>Run time</th><td>'
			. ( isset( $data['resultInfo']['runTime'] )
				? number_format( intval( $data['resultInfo']['runTime'] ) ) . 's'
				: '?'
			)
			. '</td></tr>'
			. '<tr><th>Status</th><td>'
				. htmlspecialchars( $data['resultInfo']['status'] )
			. '</td></tr>'
			. '<tr><th>Total</th><td>'
				. htmlspecialchars( $data['resultInfo']['total'] )
			. '</td></tr>'
			. '<tr><th>Fail</th><td>'
				. htmlspecialchars( $data['resultInfo']['fail'] )
			. '</td></tr>'
			. '<tr><th>Error</th><td>'
				. htmlspecialchars( $data['resultInfo']['error'] )
			. '</td></tr>'
			. '<tr><th>Started</th><td>'
				. self::getPrettyDateHtml( $data['resultInfo'], 'started' )
			. '</td></tr>'
			. ( isset( $data['resultInfo']['savedLocalFormatted'] )
				? ('<tr><th>Saved</th><td>'
					. self::getPrettyDateHtml( $data['resultInfo'], 'saved' )
					. '</td></tr>'
				)
				: ''
			)
			. '<tr><th>Results size</th><td>'
				. 'compressed: '
				. self::formatBytes( $data['resultInfo']['reportHtmlCompressedSize'] )
				. ' / uncompressed: '
				. self::formatBytes( $data['resultInfo']['reportHtmlSize'] )
				. ' / ratio: '
				. $data['resultInfo']['reportHtmlCompressionRatio']
				. '%'
			. '</td></tr>'
			. '</tbody></table>';

		$html .= '<h3>Results</h3>'
			. '<p class="swarm-toollinks">'
			. html_tag( 'a', array(
				'href' => swarmpath( 'index.php' ) . '?' . http_build_query(array(
					'action' => 'result',
					'item' => $data['resultInfo']['id'],
					'raw' => '',
				)),
				'target' => '_blank',
				'class' => 'swarm-popuplink',
			), 'Open in new window' )
			. '</p>'
			. html_tag( 'iframe', array(
				'src' => swarmpath( 'index.php' ) . '?' . http_build_query(array(
					'action' => 'result',
					'item' => $data['resultInfo']['id'],
					'raw' => '',
				)),
				'width' => '100%',
				'class' => 'swarm-result-frame',
			));


		return $html;
	}

	protected function formatBytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 

		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 

		// Uncomment one of the following alternatives
		$bytes /= pow(1024, $pow);
		// $bytes /= (1 << (10 * $pow)); 

		return round($bytes, $precision) . ' ' . $units[$pow]; 
	} 
	
	protected function serveRawResults( $resultsID ) {
		$db = $this->getContext()->getDB();

		$this->setRobots( 'noindex,nofollow' );

		$row = $db->getRow(str_queryf(
			'SELECT
				status,
				report_html
			FROM runresults
			WHERE id = %u;',
			$resultsID
		));

		header( 'Content-Type: text/html; charset=utf-8' );
		if ( $row ) {
			$status = intval( $row->status );
			// If it finished or was aborted, there should be
			// a (at least partial) html report.
			if ( $status === ResultAction::$STATE_FINISHED || $status === ResultAction::$STATE_ABORTED || $status === ResultAction::$STATE_HEARTBEAT ) {
				if ( $row->report_html ) {
					header( 'Content-Encoding: gzip' );
					echo $row->report_html;
				} else {
					$this->outputMini(
						'No Content',
						'Client saved results  but did not attach an HTML report.'
					);
				}

			// Client timed-out
			} elseif ( $status === ResultAction::$STATE_LOST ) {
				$this->outputMini(
					'Client Lost',
					'Client lost connection with the swarm.'
				);

			// Still busy? Or some unknown status?
			} else {
				$this->outputMini(
					'In Progress',
					'Client did not submit results yet. Please try again later.'
				);
			}
		} else {
			self::httpStatusHeader( 404 );
			$this->outputMini( 'Not Found' );
		}

		// This is a raw HTML response, the Page should not build.
		exit;
	}
}
