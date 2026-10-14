<?php
use Tests\TestCase;
uses(TestCase::class);
test('health endpoint is registered',function(){ $this->getJson('/api/health')->assertStatus(200)->assertJsonStructure(['status','database','redis','arc']); });
