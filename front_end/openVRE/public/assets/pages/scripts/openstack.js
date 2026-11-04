$(document).ready(function () {
	const apiBaseUrl = (window.objStorageConfig && window.objStorageConfig.url) || '/applib/objStorage.php';
	const loader = $('#loading-datatable');
	const generalPanel = $('#general');
	const dropdown = $('#containerDropdown');

	$("#errorsTool").hide();
	generalPanel.hide();
	dropdown.hide();
	if (loader.length) {
		loader.hide();
	}

	function setLoading(isLoading) {
		if (loader.length) {
			if (isLoading) {
				loader.show();
			} else {
				loader.hide();
			}
		}
	}

	function showLoading() {
		setLoading(true);
	}

	function hideLoading() {
		setLoading(false);
	}

	function showError(message) {
		const errorsTool = $('#errorsTool');
		errorsTool
			.html('<div class="alert alert-danger"><strong>Error:</strong> ' + message + '</div>')
			.fadeIn();
		hideLoading();
	}

	function showSuccess(message) {
		const errorsTool = $('#errorsTool');
		errorsTool
			.html('<div class="alert alert-success"><strong>Success:</strong> ' + message + '</div>')
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

	function populateBucketDropdown(containers) {
		const dropdown = $('#containerDropdown');
		dropdown.empty();

		if (!Array.isArray(containers) || containers.length === 0) {
			dropdown.append($('<option></option>').text('No buckets available'));
			dropdown.prop('disabled', true);
			dropdown.show();
			$('#general').show();
			return;
		}

		dropdown.prop('disabled', false);
		containers.forEach(function (container) {
			const option = $('<option></option>').val(container).text(container);
			dropdown.append(option);
		});

		dropdown.show();
		$('#general').show();
	}

	var urlJSON = apiBaseUrl;
	var credential_data = '';
	table = [];

	function loadBuckets() {
		const payload = {
			site: objectStorageSite,
			action: 'getS3User'
		};

		console.log('Loading S3 buckets:', payload);
		showLoading();

		$.ajax({
			async: true,
			type: 'GET',
			url: urlJSON,
			data: payload,
			dataType: 'json',
			cache: false,
			timeout: 30000,
			headers: {
				'Cache-Control': 'no-cache, no-store, must-revalidate',
				'Pragma': 'no-cache',
				'Expires': '0'
			}
		}).done(function (data) {
			console.log('S3 response:', data);
			if (data && data.error) {
				console.error('Backend error:', data.message);
				showError(data.message);
				return;
			}

			const containers = normalizeStorageResponse(data);
			populateBucketDropdown(containers);
			if ($('#containerDropdown option').length > 0) {
				$('#containerDropdown').val($('#containerDropdown option:first').val()).trigger('change');
			}
		}).fail(function (jqXHR, textStatus, errorThrown) {
			console.error('AJAX request failed:', textStatus, errorThrown);
			let message = 'AJAX request failed.';
			try {
				const res = JSON.parse(jqXHR.responseText);
				message = res.message || res.error || message;
			} catch (e) {
				console.error('Invalid JSON response:', jqXHR.responseText);
			}
			showError(message);
		}).always(function () {
			hideLoading();
		});
	}

	$('#containerDropdown').on('change', function () {
		const container = $(this).val();
		if (!container) {
			return;
		}
		showLoading();
		$.ajax({
			type: 'POST',
			url: urlJSON,
			async: true,
			cache: false,
			data: {
				'action': 'getContainerFiles',
				'container': container,
				'site': objectStorageSite
			},
			success: function (response) {
				try {
					const json = typeof response === 'string' ? JSON.parse(response) : response;
					const files = json && json.files ? json.files : (Array.isArray(json) ? json : []);
					const tableBody = document.getElementById('repository-body');
					if (!tableBody) {
						return;
					}
					tableBody.innerHTML = '';
					renderStorageTree(buildStorageTree(files), tableBody, 0, null);
					attachStorageTreeHandlers();
				} catch (e) {
					console.error('Error parsing files JSON:', e);
					showError('Could not parse files response.');
				}
			},
			error: function (xhr, status, error) {
				console.error('Error fetching files:', error);
				showError('Error fetching files for bucket: ' + container);
			},
			complete: function () {
				hideLoading();
			}
		});
	});

	loadBuckets();

	function safeStorageId(path) {
		return 'storage_' + String(path).replace(/[^A-Za-z0-9_-]/g, '_');
	}

	function formatStorageBytes(bytes) {
		if (bytes == null || isNaN(bytes)) return '-';
		const b = Number(bytes);
		if (b === 0) return '0 B';
		const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
		const i = Math.floor(Math.log(b) / Math.log(1024));
		return parseFloat((b / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];
	}

	function buildStorageTree(files) {
		if (!Array.isArray(files)) {
			return [];
		}

		const root = [];
		const nodesByPath = new Map();

		function ensureNode(path, isDir, meta) {
			const normalized = String(path || '').replace(/^\/+/, '').replace(/\/+$/, '');
			if (!normalized) {
				return null;
			}

			if (!nodesByPath.has(normalized)) {
				const node = {
					id: safeStorageId(normalized),
					path: normalized,
					name: meta && meta.displayName ? meta.displayName : normalized.split('/').filter(Boolean).pop() || normalized,
					displayName: meta && meta.displayName ? meta.displayName : normalized.split('/').filter(Boolean).pop() || normalized,
					size: meta && meta.size != null ? Number(meta.size) : 0,
					children: [],
					isDir: !!isDir
				};
				nodesByPath.set(normalized, node);
			}

			const node = nodesByPath.get(normalized);
			node.isDir = node.isDir || !!isDir;
			if (meta && meta.size != null) {
				node.size = Number(meta.size) || 0;
			}
			if (meta && meta.displayName) {
				node.displayName = meta.displayName;
				node.name = meta.displayName;
			}
			return node;
		}

		files.forEach(function (file) {
			const rawName = file && (file.Name || file.name || file.key || file.path || file.Prefix || '');
			if (!rawName) {
				return;
			}

			const isDir = !!(file.isDir || file.Prefix || String(rawName).endsWith('/') || file.path && String(file.path).endsWith('/'));
			const normalized = String(rawName).replace(/^\/+/, '').replace(/\/+$/, '');
			if (!normalized) {
				return;
			}

			const segments = normalized.split('/').filter(Boolean);
			let currentPath = '';
			let parent = null;

			segments.forEach(function (segment, index) {
				currentPath = currentPath ? currentPath + '/' + segment : segment;
				const lastSegment = index === segments.length - 1;
				const node = ensureNode(currentPath, lastSegment ? isDir : true, {
					displayName: segment,
					size: lastSegment ? (file.size || file.Size || file.bytes || 0) : 0
				});

				if (!node) {
					return;
				}

				if (parent) {
					if (!parent.children.some(function (child) { return child.path === node.path; })) {
						parent.children.push(node);
					}
				} else if (!root.some(function (item) { return item.path === node.path; })) {
					root.push(node);
				}

				parent = node;
			});
		});

		function sortNode(node) {
			if (node.children && node.children.length) {
				node.children.sort(function (a, b) {
					if (a.isDir !== b.isDir) {
						return a.isDir ? -1 : 1;
					}
					return String(a.displayName || a.name).localeCompare(String(b.displayName || b.name));
				});
				node.children.forEach(sortNode);
			}
		}

		root.sort(function (a, b) {
			if (a.isDir !== b.isDir) {
				return a.isDir ? -1 : 1;
			}
			return String(a.displayName || a.name).localeCompare(String(b.displayName || b.name));
		});
		root.forEach(sortNode);

		return root;
	}

	function renderStorageTree(items, parentEl, depth, parentId) {
		items.forEach(function (item) {
			const row = document.createElement('tr');
			const indent = depth * 18;
			const isDirectory = !!(item.isDir || (item.children && item.children.length > 0));
			const hasChildren = !!(item.children && item.children.length > 0);
			const uniqueId = safeStorageId(item.path || item.name || item.displayName || 'tree-row');

			row.dataset.depth = depth;
			row.dataset.parentId = parentId || '';
			row.setAttribute('data-id', uniqueId);
			if (parentId) {
				row.classList.add('child-of-' + parentId);
				row.style.display = 'none';
			}

			if (isDirectory) {
				row.classList.add('dir-row');
				row.innerHTML = [
					'<td style="padding-left:' + (indent + 10) + 'px; display:flex; align-items:center; gap:8px;">',
					'  <button type="button" class="btn btn-xs btn-default folder-toggle" data-target="' + uniqueId + '" aria-label="toggle folder" aria-expanded="false" ' + (isDirectory ? '' : 'disabled') + '>',
					'    <i class="fa fa-caret-right"></i>',
					'  </button>',
					'  <i class="fa fa-folder-open folder-icon"></i>',
					'  <span class="folder-name">' + (item.displayName || item.name) + '</span>',
					'</td>',
					'<td class="storage-size-cell">' + (item.size ? formatStorageBytes(item.size) : '-') + '</td>',
					'<td class="text-center">',
					'  <div class="actions-container">',
					'    <button type="button" class="btn btn-xs btn-default dir-action-btn disabled" title="No actions" disabled><i class="fa fa-list"></i></button>',
					'  </div>',
					'</td>'
				].join('');
				parentEl.appendChild(row);
				if (hasChildren) {
					renderStorageTree(item.children, parentEl, depth + 1, uniqueId);
				}
				return;
			}

			row.classList.add('file-row');
			row.innerHTML = [
				'<td style="padding-left:' + (indent + 10) + 'px; display:flex; align-items:center; gap:8px;">',
				'  <span class="file-indent" aria-hidden="true"></span>',
				'  <i class="fa fa-file-o file-icon"></i>',
				'  <span>' + (item.displayName || item.name) + '</span>',
				'</td>',
				'<td class="storage-size-cell">' + formatStorageBytes(item.size) + '</td>',
				'<td class="text-center">',
				'  <div class="actions-container">',
				'    <button type="button" class="btn btn-xs btn-default dir-action-btn disabled" title="No actions" disabled><i class="fa fa-download"></i></button>',
				'  </div>',
				'</td>'
			].join('');
			parentEl.appendChild(row);
		});
	}

	function attachStorageTreeHandlers() {
		const repoBody = document.getElementById('repository-body');
		if (!repoBody) {
			return;
		}

		function getRowMap() {
			const map = {};
			Array.from(repoBody.querySelectorAll('tr[data-id]')).forEach(function (row) {
				map[row.getAttribute('data-id')] = row;
			});
			return map;
		}

		function rowIsVisible(row, rowMap) {
			if (!row) {
				return false;
			}
			const parentId = row.dataset.parentId;
			if (!parentId) {
				return true;
			}

			const parentRow = rowMap[parentId];
			if (!parentRow) {
				return true;
			}

			const parentToggle = parentRow.querySelector('.folder-toggle');
			const parentOpen = !parentToggle || parentToggle.disabled || parentToggle.classList.contains('open');
			return parentOpen && rowIsVisible(parentRow, rowMap);
		}

		function syncTreeVisibility() {
			const rowMap = getRowMap();
			Array.from(repoBody.querySelectorAll('tr')).forEach(function (row) {
				row.style.display = rowIsVisible(row, rowMap) ? 'table-row' : 'none';
			});
		}

		repoBody.onclick = function (e) {
			const toggleBtn = e.target.closest('.folder-toggle');
			if (!toggleBtn) {
				return;
			}
			e.stopPropagation();
			if (toggleBtn.disabled) {
				return;
			}

			const tr = toggleBtn.closest('tr');
			if (!tr) {
				return;
			}

			const expanded = !toggleBtn.classList.contains('open');
			toggleBtn.classList.toggle('open', expanded);
			toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			const chevron = toggleBtn.querySelector('i');
			if (chevron) {
				chevron.classList.toggle('fa-rotate-90', expanded);
			}

			syncTreeVisibility();
		};
	}
});

