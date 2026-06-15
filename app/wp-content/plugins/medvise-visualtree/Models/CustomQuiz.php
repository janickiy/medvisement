<?php


namespace Medvisement\Models;

use Illuminate\Database\Eloquent\Model;
use Jiaxincui\ClosureTable\Traits\ClosureTable;

class CustomQuiz extends Model
{
	use ClosureTable;

	public $timestamps = false;

	protected $fillable = [
		'id',
		'name',
		'parent',
		'post_id',
		'position'
	];

	protected $table = 'vt_custom_quiz_entity';

	protected $closureTable = 'vt_custom_quiz_closure';

	protected $ancestorColumn = 'ancestor';

	protected $descendantColumn = 'descendant';

	protected $distanceColumn = 'distance';

	protected $parentColumn = 'parent';

}