<?php
require_once("../../config.php");
global $DB;
require_once($CFG->dirroot.'/lib/moodlelib.php');

$course_id = required_param('id', PARAM_INT);
require_login($course_id);
$context = context_course::instance($course_id);
require_capability('block/analytics_graphs:viewpages', $context);

$sql = "SELECT gi.id, categoryid, fullname, itemname, gradetype, grademax, grademin
	        FROM {grade_categories} AS gc
	        LEFT JOIN {grade_items} AS gi ON gc.courseid = gi.courseid AND gc.id = gi.categoryid
	        WHERE gc.courseid = ? AND categoryid IS NOT NULL AND EXISTS (
                SELECT * 
	                FROM mdl_grade_grades AS gg
	                WHERE gg.itemid = gi.id AND gg.rawgrade IS NOT NULL )
        ORDER BY fullname, itemname";

$result = $DB->get_records_sql($sql, array($course_id));
?>

<html>
	<head>
		<meta charset=utf-8>
		<title><?php echo get_string('grades_chart', 'block_analytics_graphs'); ?></title>
		<script src="http://code.jquery.com/jquery-1.11.3.min.js"></script>
		<script src="http://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
		<script src="http://code.highcharts.com/highcharts.js"></script>
		<script src="http://code.highcharts.com/highcharts-more.js"></script>
		<script src="http://code.highcharts.com/modules/no-data-to-display.js"></script>
	</head>
	<body>
		<div id="tasks_div"></div>
		<div id='chart_div'></div>
		<script>
			var base_chart_options = {
		        chart: {
		            type: 'boxplot'
		        },

		        title: {
		            text: 'Grades distribution'
		        },

		        legend: {
		            enabled: false
		        },

		        credits: {
		        	enabled: false
		        },

		        lang: {
		        	noData: "Toggle the grades displayed on the chart using the buttons above"
		        },

		        xAxis: {
		        	categories: [],
		            title: {
		                text: 'Task name',
		                style: {
		                	fontWeight: 'bold',
		                	fontSize: 12
		                }
		            },
		            labels: {
		            	style:{
		            		fontSize: 12,
		            	}
		            }
		        },

		        yAxis: {
		        	min: 0,
		        	max: 1,
		            title: {
		                text: 'Grades',
		                style: {
		                	fontWeight: 'bold',
		                	fontSize: 12
		                }
		            }		        
		        },

		        tooltip:{
		        	backgroundColor: "rgba(255,255,255,1.0)",
		        	formatter: function(){
		        		var str = "";
		        		str += "<b>Task " + this.point.category + "</b><br/>";
		        		str += "Total grades: " + this.point.num_grades + "<br/>";
		        		str += "Lowest grade: " + this.point.low.toFixed(2) + "<br/>";
		        		str += "Largest grade: " + this.point.high.toFixed(2) + "<br/>";
		        		str += "75% of all grades are greater than " + this.point.q1.toFixed(2) + "<br/>";
		        		str += "50% of all grades are greater than " + this.point.median.toFixed(2) + "<br/>";
		        		str += "25% of all grades are greater than " + this.point.q3.toFixed(2) + "<br/>";
		        		return str;
		        	}
		        },

		        plotOptions: {
		            boxplot: {
		            	pointWidth: 50,
		                fillColor: '#F0F0E0',
		                lineWidth: 2,
		                medianColor: '#3333FF',
		                medianWidth: 4,
		                stemColor: '#434348',
		                stemDashStyle: 'dot',
		                stemWidth: 1.5,
		                whiskerColor: '#669999',
		                whiskerLength: '20%',
		                whiskerWidth: 3
		            }
		        },

		        series: [{
		        }]
		    };
			var tasks = <?php echo json_encode($result); ?>;
			var totaltasks = tasks.length;
			var tasks_toggle = {};
			var taskidname = {};
			var active_tasks = 0;
			for(elem in tasks){
				$("#tasks_div").append("<div class='individual_task_divs' id='div_task_" + tasks[elem]['id'] + "'>" + 
										"<button type='button' class=task_button id='" +  tasks[elem]['id'] + "'>" + 
										tasks[elem]['itemname'] + "</button></div>");
				tasks_toggle[tasks[elem]['id']] = false;
				taskidname[tasks[elem]['id']] = tasks[elem]['itemname'];
			}
			$("#chart_div").highcharts(base_chart_options);
			$('.task_button').click(function(){
				var task_name = this.id;
				var send_data = [];
				if(tasks_toggle[task_name] === true){
					tasks_toggle[task_name] = false;
					active_tasks--;
				}
				else{
					tasks_toggle[task_name] = true;
					active_tasks ++;
				}
				$('#chart_div').highcharts().xAxis[0].categories = [];
				if(active_tasks > 0){
					for(var field in tasks_toggle){
						if(tasks_toggle[field] === true){
							send_data.push(field.toString());
							$('#chart_div').highcharts().xAxis[0].categories.push(taskidname[field.toString()]);
						}
					}
					$.ajax({
						type: "POST",
						dataType: "JSON",
						url: "query_grades.php",
						data: {
							"form_data": send_data,
							"course_id": <?php echo json_encode($course_id); ?>
						},
						success: function(grades_info){
							var grades_stats = [];
							var sort_func = function(a, b){
								return a - b;
							};
							var median_func = function(data){
								var data_size = data.length;
								if(data_size % 2){
									return data[Math.floor(data_size/2)];
								}
								else{
									return 0.5 * (data[data_size/2] + data[data_size/2 - 1]);
								}
							};
							for(var task_i in grades_info){
								var num_grades = grades_info[task_i]['grades'].length;
								var task_data = null;
								var min_grade = Math.min.apply(null, grades_info[task_i]['grades']);
								var max_grade = Math.max.apply(null, grades_info[task_i]['grades']);
								var median_grade = median_func(grades_info[task_i]['grades']);
								var q1_grade = null, q3_grade = null;
								if(num_grades%2){
									q1_grade = median_func(grades_info[task_i]['grades'].slice(0,Math.max(Math.floor(num_grades/2), 1)));
									q3_grade = median_func(grades_info[task_i]['grades'].slice(Math.min(Math.floor(num_grades/2) + 1, num_grades-1), Math.max(num_grades, Math.floor(num_grades/2) + 1)));
								}
								else{
									q1_grade = median_func(grades_info[task_i]['grades'].slice(0,num_grades/2));
									q3_grade = median_func(grades_info[task_i]['grades'].slice(num_grades/2, num_grades));
								}
								task_data = {
								    low: min_grade,
								    q1: q1_grade,
								    median: median_grade,
								    q3: q3_grade,
								    high: max_grade,
								    name: task_i,
								    num_grades: num_grades
								};
								grades_stats.push(task_data);
							}
							$('#chart_div').highcharts().series[0].setData(grades_stats);
						}
					});
				}
				else{
					$('#chart_div').highcharts().series[0].setData([]);
				}
				return false;
			});			
		</script>
	</body>
</html>