<?php
namespace App\Dto;

class RestrictionDto
{
    public int $days = 0;
    public bool $canPost = false;
    public bool $canComment = false;
    public bool $canCreateForum = false;
    public string $reason = '';
    public int $offenseNumber = 1;
}
