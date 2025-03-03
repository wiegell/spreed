<!--
 - @copyright Copyright (c) 2021 Daniel Calviño Sánchez <danxuliu@gmail.com>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<div id="web_server_setup_checks" class="section">
		<h2>
			{{ t('spreed', 'Web server setup checks') }}
		</h2>

		<ul class="web-server-setup-checks">
			<li class="background-blur">
				{{ t('spreed', 'Files required for background blur can be loaded') }}
				<button
					v-if="backgroundBlurAvailable === false"
					v-tooltip="backgroundBlurAvailableToolTip"
					:aria-label="backgroundBlurAvailableAriaLabel"
					class="icon"
					:class="backgroundBlurAvailableClasses"
					@click="checkBackgroundBlur" />
				<button
					v-else-if="backgroundBlurAvailable === true"
					v-tooltip="backgroundBlurAvailableToolTip"
					:aria-label="backgroundBlurAvailableAriaLabel"
					class="icon"
					:class="backgroundBlurAvailableClasses"
					@click="checkBackgroundBlur" />
				<span
					v-else
					v-tooltip="backgroundBlurAvailableToolTip"
					:aria-label="backgroundBlurAvailableAriaLabel"
					class="icon"
					:class="backgroundBlurAvailableClasses" />
			</li>
		</ul>
	</div>
</template>

<script>
import { generateFilePath } from '@nextcloud/router'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { VIRTUAL_BACKGROUND_TYPE } from '../../utils/media/effects/virtual-background/constants'
import JitsiStreamBackgroundEffect from '../../utils/media/effects/virtual-background/JitsiStreamBackgroundEffect'
import VirtualBackground from '../../utils/media/pipeline/VirtualBackground'

export default {
	name: 'WebServerSetupChecks',

	directives: {
		tooltip: Tooltip,
	},

	data() {
		return {
			backgroundBlurLoaded: undefined,
		}
	},

	computed: {
		backgroundBlurAvailable() {
			return this.backgroundBlurLoaded
		},

		backgroundBlurAvailableClasses() {
			return {
				'icon-checkmark': this.backgroundBlurAvailable === true,
				'icon-error': this.backgroundBlurAvailable === false,
				'icon-loading-small': this.backgroundBlurAvailable === undefined,
			}
		},

		backgroundBlurAvailableAriaLabel() {
			if (this.backgroundBlurAvailable === false) {
				return t('spreed', 'Failed')
			}

			if (this.backgroundBlurAvailable === true) {
				return t('spreed', 'OK')
			}

			return t('spreed', 'Checking …')
		},

		backgroundBlurAvailableToolTip() {
			if (this.backgroundBlurAvailable === false && !VirtualBackground.isWasmSupported()) {
				return t('spreed', 'Failed: WebAssembly is disabled or not supported in this browser. Please enable WebAssembly or use a browser with support for it to do the check.')
			}

			if (this.backgroundBlurAvailable === false) {
				return t('spreed', 'Failed: ".wasm" and ".tflite" files were not properly returned by the web server. Please check "System requirements" section in Talk documentation.')
			}

			if (this.backgroundBlurAvailable === true) {
				return t('spreed', 'OK: ".wasm" and ".tflite" files were properly returned by the web server.')
			}

			return t('spreed', 'Checking …')
		},
	},

	beforeMount() {
		this.checkBackgroundBlur()
	},

	methods: {
		checkBackgroundBlur() {
			if (!VirtualBackground.isWasmSupported()) {
				this.backgroundBlurLoaded = false

				return
			}

			this.backgroundBlurLoaded = undefined

			// Pass only the essential options to check if the files can be
			// loaded.
			const options = {
				virtualBackground: {
					type: VIRTUAL_BACKGROUND_TYPE.NONE,
				},
				simd: VirtualBackground.isWasmSimd(),
			}

			/* eslint-disable no-undef, camelcase */

			// When the worker is loaded from Talk its URL starts with
			// "apps/spreed/js". However, when it is loaded from the
			// administration settings its URL starts with "apps/talk/js"
			// instead, so it fails to load.
			//
			// "publicPath" option in "worker-loader" configuration does not
			// work with Webpack 5. As a workaround the public path needs to be
			// overriden at runtime before loading the worker and restored
			// afterwards.
			// https://github.com/webpack-contrib/worker-loader/issues/281
			const __webpack_public_path__saved = __webpack_public_path__

			__webpack_public_path__ = generateFilePath('spreed', 'js', '')

			const jitsiStreamBackgroundEffect = new JitsiStreamBackgroundEffect(options)

			__webpack_public_path__ = __webpack_public_path__saved

			/* eslint-enable no-undef, camelcase */

			jitsiStreamBackgroundEffect.load().then(() => {
				this.backgroundBlurLoaded = true
			}).catch(() => {
				this.backgroundBlurLoaded = false
			})
		},
	},
}
</script>

<style lang="scss" scoped>
button.icon {
	background-color: transparent;
	border: none;
	width: 44px;
}
</style>
