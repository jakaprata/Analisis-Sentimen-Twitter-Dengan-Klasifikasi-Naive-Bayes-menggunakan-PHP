<html>
 <head>
        <title>Text Mining</title>
		
		<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
		<link rel="stylesheet" href="css/material.indigo-pink.min.css">
		<script defer src="js/material.min.js"></script>
		
		<!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">
		<style>
		label{
			font-weight:bolder;
		}
		</style>
    </head>
<body>

<?php 

include('PHPExcel-1.8/Classes/PHPExcel/IOFactory.php');

	// prepare positive and negative words
	$positive_words_path = "positive-words.txt";
	$negative_words_path = "negative-words.txt";
	$negation_words_path = "negation.txt";
	
	$pw = file_get_contents($positive_words_path);
	$nw = file_get_contents($negative_words_path);
	$ne = file_get_contents($negation_words_path);
	
	$pw = preg_split('/\s+/', $pw);
	$nw = preg_split('/\s+/', $nw);
	$ne = preg_split('/\s+/', $ne);
	
	$dataset1 = array_diff($pw, $nw);
	$dataset2 = array_diff($nw, $pw);
	$dataset = array_merge($dataset1, $dataset2);
	$countDataset = count($dataset);
	
	$countPw = count($pw);
	$countNw = count($nw);
		
		
	//read file 
	$data = null;
	if(isset($_POST['query']) && $_POST['query']){
		$data = array(1, [['A'=>1,'B'=>$_POST['query'], 'C'=>$_POST['queryClass'] ]]);
	}elseif (isset($_FILES['fileUpload'])) {
		  $errors="";
		  $file_name = $_FILES['fileUpload']['name'];
		  $file_size =$_FILES['fileUpload']['size'];
		  $file_tmp =$_FILES['fileUpload']['tmp_name'];
		  $file_type=$_FILES['fileUpload']['type'];
		  $file_ext=strtolower(end(explode('.',$_FILES['fileUpload']['name'])));
		 
		$file_name = $file_tmp;
		$inputFileType = PHPExcel_IOFactory::identify($file_name);
		$objReader = PHPExcel_IOFactory::createReader($inputFileType);
		$objPHPExcel = $objReader->load($file_name); 
		$data = array(1,$objPHPExcel->getActiveSheet()->toArray(null,true,true,true));
	}
	else
	{
		$i = 0;
	}

$new_data =  array();
$i = 0;
$j = 0;
if($data != NULL){
	if($data[0]==1){
		foreach($data[1] AS $row){
			$string = $row['B'];
			$regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@";
			$data =  preg_replace($regex, ' ', $string);
			$data2 =  preg_replace($regex, ' ', $data);			
			$text = $data2 . ', '; 
			$new_data[$j]['text'] = $text.'--'.'<br />';
			$new_data[$j]['class'] = $row['C'];
			$i++;
			$j++;
		}
	}
}	


	// print_r($new_data);die();
	
	//get the terms and delete the stopwords
	$stopwords = file_get_contents("./stopwords_en.txt");
	$stopwords = preg_split("/[\s]+/", $stopwords);

	$data_unclassified = [];
	$data_classified = [];
	$confussion_matrix = [
		'positive' => [
			'positive' => 0,
			'negative' => 0
		],
		'negative' => [
			'positive' => 0,
			'negative' => 0
		]
	];

	
	
	if($new_data != NULL){
		$limit = abs(1/($countDataset+$countNw) - 1/($countDataset+$countPw));
		for($x = 0; $x <count($new_data); $x++ ){
			if(!in_array($new_data[$x]['class'], ['positive','negative'])){
				array_push($data_unclassified, $new_data[$x]);
				continue;
			}

			$classified = [];
			$term = preg_split("/[\d\W\s]+/", strtolower($new_data[$x]['text']));
			$term = array_diff($term, $stopwords);	
			$term = array_values($term);	
			$term = array_count_values($term);
			
			// Priors probabilities
			$totalP = 2006/6789;
			$totalN = 4783/6789;

			$classified['text'] = $new_data[$x]['text'];

			$is_negation = 0;
			foreach($term as $key => $val){

				// check negation handling
				if(in_array($key, $ne)){
					$is_negation = 1;
					continue;
				}
				
				$classified['term'][$key] = [];
				$classified['term'][$key]['tf'] = $val;

				// calculate positive probabilities
				if((in_array($key, $pw) && !$is_negation) || (in_array($key, $nw) && $is_negation)){
					$classified['term'][$key]['positive'] = pow(((2+$is_negation)/($countDataset+$countPw)), $val);
				} else {
					$classified['term'][$key]['positive'] = 1/($countDataset+$countPw);
				}
				$totalP *= $classified['term'][$key]['positive'];
				
				// calculate negative probabilities
				if((in_array($key, $nw) && !$is_negation) || (!in_array($key, $nw) && $is_negation)){
					$classified['term'][$key]['negative'] = pow(((2+$is_negation)/($countDataset+$countNw)), $val);
				} else {
					$classified['term'][$key]['negative'] = 1/($countDataset+$countNw);
				}
				$totalN *= $classified['term'][$key]['negative'];

				if($is_negation) $is_negation = 0;
			}
			
			$classified['positive'] = $totalP;
			$classified['negative'] = $totalN;
			$classified['actual'] = $new_data[$x]['class'];
			$classified['difference'] = abs($totalP - $totalN);

			if($totalP > $totalN){
				$classified['predicted'] = 'positive';
			} else {
				$classified['predicted'] = 'negative';
			}

			$confussion_matrix[$new_data[$x]['class']][$classified['predicted']]++;

			array_push($data_classified, $classified);

		}	
		echo "Unclassified : ". count($data_unclassified);
		echo "</br >";
		echo "Classified : ". count($data_classified);
		echo "</br >";

		$i = 1;
		?>
		<h3>Hasil klasifikasi tweet</h3>

		<table border="1">
			<thead>
				<tr>
					<th>No</th>
					<th>Tweet</th>
					<th>Positive</th>
					<th>Negative</th>
					<th>Difference</th>
					<th>Predicted</th>
					<th>Actual</th>
					<th>TRUE</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($data_classified as $row) {
				if(isset($row['term'])){
			?>
				<tr>
					<td><?= $i++ ?></td>
					<td>
						<table border="1">
							<tr>
								<td><?= $row['text'] ?></td>
							</tr>
							<tr>
								<td>
									<table border="1">
										<tr>
											<td>&nbsp;</td>
											<?php foreach($row['term'] as $term => $term_v){ ?>
											<td><?= $term ?></td>
											<?php } ?>
										</tr>
										<tr>
											<td>Term Frequency</td>
											<?php foreach($row['term'] as $term_v){ ?>
											<td><?= $term_v['tf'] ?></td>
											<?php } ?>
										</tr>
										<tr>
											<td>Positive</td>
											<?php foreach($row['term'] as $term_v){ ?>
											<td><?= $term_v['positive'] ?></td>
											<?php } ?>
										</tr>
										<tr>
											<td>Negative</td>
											<?php foreach($row['term'] as $term_v){ ?>
											<td><?= $term_v['negative'] ?></td>
											<?php } ?>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</td>
					<td><?= $row['positive'] ?></td>
					<td><?= $row['negative'] ?></td>
					<td><?= $row['difference'] ?></td>
					<td><?= $row['predicted'] ?></td>
					<td><?= $row['actual'] ?></td>
					<td><?= $row['actual'] == $row['predicted'] ? 'YES' : 'NO' ?></td>
				</tr>
			<?php
			}} ?>
			</tbody>
		</table>

		<h3>Confussion Matrix</h3>
		<table border="1">
			<tr style="font-weight: bold;">
				<td>Actual / Predicted</td>
				<td>Positive</td>
				<td>Negative</td>
				<td>Total</td>
			</tr>
			<tr>
				<td style="font-weight: bold;">Positive</td>
				<td><?= $positive = $confussion_matrix['positive']['positive'] ?></td>
				<td><?= $confussion_matrix['positive']['negative'] ?></td>
				<td style="font-weight: bold;"><?= $actual_positive = $confussion_matrix['positive']['positive'] + $confussion_matrix['positive']['negative'] ?></td>
			</tr>
			<tr>
				<td style="font-weight: bold;">Negative</td>
				<td><?= $confussion_matrix['negative']['positive'] ?></td>
				<td><?= $negative = $confussion_matrix['negative']['negative'] ?></td>
				<td style="font-weight: bold;"><?= $actual_negative = $confussion_matrix['negative']['positive'] + $confussion_matrix['negative']['negative'] ?></td>
			</tr>
			<tr>
				<td style="font-weight: bold;">Total</td>
				<td style="font-weight: bold;"><?= $pos = $confussion_matrix['positive']['positive'] + $confussion_matrix['negative']['positive'] ?></td>
				<td style="font-weight: bold;"><?= $neg = $confussion_matrix['positive']['negative'] + $confussion_matrix['negative']['negative'] ?></td>
				<td style="font-weight: bold;"><?= $total = $pos + $neg ?></td>
			</tr>
		</table>

		<h3>Hasil Evaluasi</h3>
		<table border="1">
			<thead>
				<tr>
					<th>Evaluasi</th>
					<th>Nilai</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td style="font-weight: bold;">Akurasi</td>
					<td><?= ($positive+$negative)/$total ?></td>
				</tr>
				<tr>
					<td style="font-weight: bold;">Recall Positive</td>
					<td><?= $confussion_matrix['positive']['positive']/$actual_positive ?></td>
				</tr>
				<tr>
					<td style="font-weight: bold;">Precision Positive</td>
					<td><?= $confussion_matrix['positive']['positive']/$pos ?></td>
				</tr>
				<tr>
					<td style="font-weight: bold;">Recall Negative</td>
					<td><?= $confussion_matrix['negative']['negative']/$actual_negative ?></td>
				</tr>
				<tr>
					<td style="font-weight: bold;">Precision Negative</td>
					<td><?= $confussion_matrix['negative']['negative']/$neg ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}			
?>

 <?php if (!empty($_POST["name"])) echo $_POST["compression"]; ?><br>
	<div class="container">
		<nav class="navbar navbar-dark bg-faded" style="background-image:url(img/header.png);background-size: auto 100%;">
		
		<a class="navbar-brand" href="#">Text Mining</a>
		</nav>
		<div class="container">
			<br/>
			<br/>
			<form  action="" method="post" enctype="multipart/form-data">
				<div class="row">
					<div class="col-md-3">
						<label>Upload File Dokumen</label>
					</div>
					<div class="col-md-3">
						<input type="file" name="fileUpload">
					</div>
				</div>
				<div class="row">
					<div class="col-md-3">
						<input type="text" name="query" />
						<select name="queryClass">
							<option>positive</option>
							<option>negative</option>
						</select>
					</div>
				</div>
				<button type="submit" class="mdl-button mdl-js-button mdl-button--raised mdl-button--accent">
				  Upload
				</button>
			</form>		
	</div>
</body>
</html>
