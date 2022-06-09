<?php
declare(strict_types = 1);

namespace SkyWars\forms\elements;
class StepSlider extends Dropdown {
	/**
	 * @return string
	 */
	public function getType(): string{
		return "step_slider";
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeElementData(): array{
		return [
			"steps"   => $this->options,
			"default" => $this->default,
		];
	}
}
