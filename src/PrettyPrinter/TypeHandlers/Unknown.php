<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\Utils\Text;
use PrettyPrinter\TypeHandler;

final class Unknown extends TypeHandler
{
	function handleValue( &$unknown )
	{
		return Text::line( 'unknown type' );
	}
}