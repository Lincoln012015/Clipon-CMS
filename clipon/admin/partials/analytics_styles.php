    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 1rem 0.75rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-card .value {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 0.35rem;
            color: var(--text-primary);
        }
        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .analytics-chart-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            height: 400px;
            display: flex;
            flex-direction: column;
        }
        .analytics-card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .analytics-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #fcfcfd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .analytics-card-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
        }
        .analytics-table td {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }
        .analytics-table tr:last-child td {
            border-bottom: none;
        }
        .bar-bg {
            height: 8px;
            background: var(--primary-light);
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
        }
        .bar-fill {
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
        }
        .filter-bar {
            background: var(--card-bg);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        .filter-bar input, .filter-bar select {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-color);
            color: var(--text-primary);
        }
        .btn-apply {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        .btn-apply:hover {
            background: var(--primary-hover);
        }
        .bg-secondary-btn {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        .modal-overlay, .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--card-bg);
            width: 90%;
            max-width: 900px;
            max-height: 85vh;
            border-radius: var(--radius-lg);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-search {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-search input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }
        .modal-body {
            overflow-y: auto;
            flex-grow: 1;
            padding: 0;
        }
        .modal-table {
            width: 100%;
            border-collapse: collapse;
        }
        .modal-table th {
            position: sticky;
            top: 0;
            background: var(--bg-color);
            padding: 0.75rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
        }
        .modal-table td {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }
        .btn-close, .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
            line-height: 1;
            color: var(--text-secondary);
        }
        .btn-show-all {
            background: none;
            border: 1px solid var(--border-color);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            cursor: pointer;
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-show-all:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }
        .copy-hint {
            background: transparent;
            border: none;
            padding: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            opacity: 0.9;
            transition: color 0.15s ease, transform 0.15s ease, opacity 0.15s ease;
            margin-left: 0.5rem;
            line-height: 0;
        }
        .copy-hint:hover {
            color: var(--primary-color);
            transform: scale(1.05);
            opacity: 1;
        }
        .copy-hint.copied {
            color: var(--success-color);
            transform: scale(1.08);
            opacity: 1;
        }
        .form-group {
            margin-bottom: 1rem;
            padding: 0 1.5rem;
        }
        .form-group:first-of-type {
            margin-top: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-color);
            color: var(--text-primary);
        }
        .modal-actions {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
    </style>
