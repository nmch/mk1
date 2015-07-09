<?
return [
	'preset' => [
		// ********************************************************************
		'preset_name' => [
			'key' => [
				'formcontrol' => [
					'name'       => 'NAME',
					'filter'     => ['hankaku', 'only0to9'],
					'filter'     => ['hankaku', 'hantozen', 'trim'],
					'validation' => ['required'],
				],
			],
		],
	],
];

