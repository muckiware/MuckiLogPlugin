<?php
/**
 * MuckiLogPlugin
 *
 * @category   SW6 Plugin
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace MuckiLogPlugin\Core;

enum SendMailMode: string
{
    case EventMail = 'eachEventOneMail';
    case LogLevelMail = 'eachLogLevelOneMail';
}
