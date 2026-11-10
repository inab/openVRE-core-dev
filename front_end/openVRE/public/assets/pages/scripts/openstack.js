$(document).ready(function () {
	const apiBaseUrl = (window.objStorageConfig && window.objStorageConfig.url) || '/applib/objStorage.php';
	const dropdown = $('#containerDropdown');
	const loadBucketsBtn = $('#loadBucketsBtn');
	const tableBody = $('#repository-body');
	const siteId = window.objectStorageSite || (window.objStorageConfig && window.objStorageConfig.site);
	const storageState = { currentContainer: null };
	const storageToolbar = $('#storageToolbar');
	const storageTableWrap = $('#storageTableWrap');

	function setDropdownVisibility(visible) {
		if (visible) {
			dropdown.show();
			storageToolbar.show();
		} else {
			dropdown.hide();
			storageToolbar.hide();
		}
	}

	function setTableVisibility(visible) {
		storageTableWrap.toggle(visible);
	}

	function showLoading() {
		$('#loading-datatable').length && $('#loading-datatable').show();
	}

	function hideLoading() {
		$('#loading-datatable').length && $('#loading-datatable').hide();
	}

	function showError(message) {
		$('#errorsTool')
			.html('<div class="alert alert-danger"><strong>Error:</strong> ' + message + '</div>')
			.fadeIn();
		hideLoading();
	}

	function normalizeStorageResponse(data) {
		if (data === null || data === undefined) {
			return [];
		}
		if (typeof data === 'string') {
			try {
				return normalizeStorageResponse(JSON.parse(data));
			} catch (e) {
				console.warn('Could not parse storage response string:', data);
				return [];
			}
		}
		if (Array.isArray(data)) {
			return data;
		}
		if (Array.isArray(data.containers)) {
			return data.containers;
		}
		if (Array.isArray(data.buckets)) {
			return data.buckets;
		}
		return [];
	}

	function buildRequestPayload(action, extraData) {
		return Object.assign({
			site: siteId,
			action: action
		}, extraData || {});
	}

	function requestStorage(action, data, onSuccess, onError) {
		showLoading();
		$.ajax({
			async: true,
			type: 'GET',
			url: apiBaseUrl,
			data: buildRequestPayload(action, data),
			dataType: 'json',
			cache: false,
			timeout: 30000,
			headers: {
				'Cache-Control': 'no-cache, no-store, must-revalidate',
				'Pragma': 'no-cache',
				'Expires': '0'
			}
		}).done(function (response) {
			if (response && response.error) {
				if (typeof onError === 'function') {
					onError(response.message || 'Request failed.');
					return;
				}
				showError(response.message || 'Request failed.');
				return;
			}
			if (typeof onSuccess === 'function') {
				onSuccess(response);
			}
		}).fail(function (jqXHR, textStatus, errorThrown) {
			let message = 'AJAX request failed.';
			try {
				const res = JSON.parse(jqXHR.responseText);
				message = res.message || res.error || message;
			} catch (e) {
				console.error('Invalid JSON response:', jqXHR.responseText);
			}
			console.error('Storage request failed', {
				action: action,
				textStatus: textStatus,
				errorThrown: errorThrown,
				message: message,
				responseText: jqXHR.responseText
			});
			if (typeof onError === 'function') {
				onError(message);
				return;
			}
			showError(message);
		}).always(function () {
			hideLoading();
		});
	}

	function clearRepositoryBody(message) {
		tableBody.empty();
		setTableVisibility(false);
		if (message) {
			console.error(message);
		}
	}

	function resetStorageUi() {
		dropdown.empty();
		dropdown.prop('disabled', true);
		setDropdownVisibility(false);
		clearRepositoryBody();
	}

	function populateBucketDropdown(containers) {
		dropdown.empty();
		if (!Array.isArray(containers) || containers.length === 0) {
			dropdown.append($('<option></option>').text('No buckets available'));
			dropdown.prop('disabled', true);
			setDropdownVisibility(true);
			setTableVisibility(false);
			return;
		}

		dropdown.prop('disabled', false);
		containers.forEach(function (container) {
			dropdown.append($('<option></option>').val(container).text(container));
		});
		setDropdownVisibility(true);
		setTableVisibility(false);
	}

	function buildStorageTree(files) {
		const root = { name: '/', type: 'dir', path: '', children: [] };

		(files || []).forEach(function (item) {
			const rawName = item && (item.Name || item.name || item.path || '');
			const normalizedName = String(rawName).replace(/\\/g, '/');
			if (!normalizedName) {
				return;
			}

			const segments = normalizedName.split('/').filter(Boolean);
			if (!segments.length) {
				return;
			}

			let current = root;
			let currentPath = '';

			segments.forEach(function (segment, index) {
				const isLast = index === segments.length - 1;
				currentPath = currentPath ? currentPath + '/' + segment : segment;

				if (isLast) {
					const fileNode = {
						name: segment,
						type: 'file',
						path: currentPath,
						size: Number(item && item.size !== undefined ? item.size : (item && item.Size || 0)),
						LastModified: item && item.LastModified ? item.LastModified : null
					};
					current.children.push(fileNode);
					return;
				}

				let dir = current.children.find(function (child) {
					return child.type === 'dir' && child.name === segment && child.path === currentPath;
				});

				if (!dir) {
					dir = {
						name: segment,
						type: 'dir',
						path: currentPath,
						children: [],
						expanded: false,
						LastModified: null
					};
					current.children.push(dir);
				}

				current = dir;
			});
		});

		return root;
	}

	function formatBytes(bytes) {
		if (!bytes || bytes === 0) {
			return '0 B';
		}

		const units = ['B', 'KB', 'MB', 'GB', 'TB'];
		const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
		const value = bytes / Math.pow(1024, exponent);
		const precision = value >= 10 || exponent === 0 ? 0 : 1;
		return value.toFixed(precision) + ' ' + units[exponent];
	}

	function renderStorageTree(tree, container, depth, parentPath) {
		tableBody.empty();
		const nodes = (tree && tree.children) ? tree.children : [];

		if (!nodes.length) {
			tableBody.append(
				$('<tr></tr>').append(
					$('<td></td>').attr('colspan', 4).text('No files found in this bucket.')
				)
			);
			return;
		}

		function renderNode(node, level) {
			const nodePath = node.path || (parentPath ? parentPath + '/' + node.name : node.name);
			const trimmedPath = nodePath.replace(/^\/+/, '');
			const row = $('<tr></tr>').attr('data-path', trimmedPath);
			const isExpanded = node.expanded === true;

			if (node.type === 'dir') {
				const toggle = $('<button type="button" class="folder-toggle ' + (isExpanded ? 'open' : 'closed') + '"></button>')
					.html('<i class="fa fa-chevron-' + (isExpanded ? 'down' : 'right') + '"></i>')
					.attr('title', isExpanded ? 'Collapse folder' : 'Expand folder');

				row.addClass('dir-row');
				row.attr('data-expanded', isExpanded ? 'true' : 'false');
				row.append(
					$('<td></td>').css('padding-left', (level * 18 + 12) + 'px').append(
						toggle,
						$('<span></span>').addClass('folder-icon').html('<i class="fa fa-folder"></i>'),
						$('<span></span>').addClass('folder-name').text(node.name)
					)
				);
				row.append($('<td></td>').text(''));
				row.append($('<td></td>').text(node.LastModified ? new Date(node.LastModified).toLocaleString() : '—'));
				row.append(
					$('<td></td>').append(
						$('<button></button>').addClass('btn btn-xs blue dir-action-btn').html('<i class="fa fa-upload"></i>').attr({
							title: 'Import directory',
							'aria-label': 'Import directory'
						}).on('click', function () {
							downloadStorageDirectory(trimmedPath, 'existing');
						}),
						' ',
						$('<button></button>').addClass('btn btn-xs green dir-action-btn').html('<i class="fa fa-plus"></i>').attr({
							title: 'Import in new workspace',
							'aria-label': 'Import in new workspace'
						}).on('click', function () {
							downloadStorageDirectory(trimmedPath, 'new');
						})
					)
				);
				tableBody.append(row);

				if (node.children && node.children.length) {
					node.children.forEach(function (child) {
						const childRow = renderNode(child, level + 1);
						if (!isExpanded && childRow) {
							childRow.hide();
						}
					});
				}
				return row;
			}

			row.addClass('file-row');
			row.append(
				$('<td></td>').css('padding-left', (level * 18 + 12) + 'px').append(
					$('<span></span>').addClass('file-icon').html('<i class="fa fa-file-o"></i>'),
					$('<span></span>').text(node.name)
				)
			);
			row.append($('<td></td>').text(formatBytes(node.size || 0)));
			row.append($('<td></td>').text(node.LastModified ? new Date(node.LastModified).toLocaleString() : '—'));
			row.append($('<td></td>').text(''));
			tableBody.append(row);
			return row;
		}

		nodes.forEach(function (node) {
			renderNode(node, 0);
		});
	}

	function attachStorageTreeHandlers() {
		tableBody.find('.folder-toggle').off('click').on('click', function () {
			const $toggle = $(this);
			const $row = $toggle.closest('tr');
			const path = $row.data('path');
			const $children = tableBody.find('tr[data-path^="' + path + '/"]');

			if ($toggle.hasClass('open')) {
				$toggle.removeClass('open').addClass('closed').html('<i class="fa fa-chevron-right"></i>');
				$toggle.attr('title', 'Expand folder');
				$row.attr('data-expanded', 'false');
				$children.hide();
			} else {
				$toggle.removeClass('closed').addClass('open').html('<i class="fa fa-chevron-down"></i>');
				$toggle.attr('title', 'Collapse folder');
				$row.attr('data-expanded', 'true');
				$children.show();
			}
		});
	}

	function downloadFile(fileName) {
		if (!storageState.currentContainer || !fileName) {
			console.error('downloadFile() called without container or fileName');
			return;
		}

		requestStorage('downloadFile', {
			container: storageState.currentContainer,
			fileName: fileName
		}, function (response) {
			const status = response && response.status ? response.status : 'success';
			if (status === 'success') {
				showError('File queued for download successfully.');
				return;
			}
			showError((response && response.message) || 'File download failed.');
		}, function (message) {
			showError(message);
		});
	}

	function downloadStorageDirectory(dirPath, mode) {
		if (!dirPath) {
			console.error('downloadStorageDirectory() called without dirPath');
			return;
		}

		const payload = {
			container: storageState.currentContainer,
			dirPath: dirPath
		};

		if (mode === 'new') {
			payload.projectName = storageState.currentContainer || 's3-import';
			requestStorage('downloadDirectoryNewWorkspace', payload, function (response) {
				if (response && response.redirect) {
					window.location.href = response.redirect;
					return;
				}
				if (response && response.message) {
					showError(response.message);
				}
			}, function (message) {
				showError(message);
			});
			return;
		}

		requestStorage('downloadDirectory', payload, function (response) {
			if (response && response.redirect) {
				window.location.href = response.redirect;
				return;
			}
			if (response && response.message) {
				showError(response.message);
			}
		}, function (message) {
			showError(message);
		});
	}

	function loadBuckets() {
		requestStorage('getS3User', {}, function (data) {
			const containers = normalizeStorageResponse(data);
			populateBucketDropdown(containers);
			const firstOption = dropdown.find('option').first();
			if (firstOption.length && firstOption.val() !== 'No buckets available') {
				dropdown.val(firstOption.val());
				storageState.currentContainer = firstOption.val();
				setTableVisibility(true);
				loadBucketFiles(firstOption.val());
			} else {
				storageState.currentContainer = null;
				setTableVisibility(false);
				clearRepositoryBody('No buckets available');
			}
		}, function (message) {
			console.error('Bucket load failed:', message);
			setTableVisibility(false);
			clearRepositoryBody('Unable to load buckets');
			showError(message);
		});
	}

	function loadBucketFiles(container) {
		if (!container) {
			console.error('loadBucketFiles() called without container');
			return;
		}
		storageState.currentContainer = container;
		setTableVisibility(true);
		requestStorage('getContainerFiles', { container: container }, function (response) {
			const files = response && response.files ? response.files : (Array.isArray(response) ? response : []);
			const tree = buildStorageTree(files);
			renderStorageTree(tree, tableBody[0], 0, null);
			attachStorageTreeHandlers();
		}, function (message) {
			console.error('Bucket file load failed:', message);
			setTableVisibility(false);
			showError('Error fetching files for bucket: ' + container + '. ' + message);
		});
	}

	dropdown.on('change', function () {
		const selected = dropdown.val();
		if (selected && selected !== 'No buckets available') {
			loadBucketFiles(selected);
		}
	});

	loadBucketsBtn.on('click', function () {
		loadBuckets();
	});

	resetStorageUi();
	$('#errorsTool').hide();
});

