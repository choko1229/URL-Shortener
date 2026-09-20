<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * UI で使うストロークアイコン（design.md 4. アイコン: 塗りなし・stroke-width 2・絵文字不可）。
 * SVG の中身はここで定義した固定文字列のみを出力する（利用者入力は混ざらない）。
 */
enum IconName: string
{
    case Logo = 'logo';
    case LogIn = 'log-in';
    case LogOut = 'log-out';
    case Lock = 'lock';
    case Clock = 'clock';
    case Pencil = 'pencil';
    case Copy = 'copy';
    case QrCode = 'qr-code';
    case Shield = 'shield';
    case BarChart = 'bar-chart';
    case Trash = 'trash';
    case ChevronDown = 'chevron-down';
    case Close = 'close';
    case AlertCircle = 'alert-circle';
    case CheckCircle = 'check-circle';
    case Info = 'info';
    case Key = 'key';
    case Ban = 'ban';
    case Sliders = 'sliders';
    case LayoutGrid = 'layout-grid';
    case Users = 'users';
    case ExternalLink = 'external-link';
    case Link = 'link';
    case Refresh = 'refresh';
    case Plus = 'plus';
    case Plug = 'plug';
    case Mail = 'mail';
    case Share = 'share';

    public function svgContent(): string
    {
        return match ($this) {
            self::Share => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
            self::Mail => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            self::Plug => '<path d="M9 2v6M15 2v6"/><path d="M6 8h12v3a6 6 0 0 1-12 0Z"/><path d="M12 17v5"/>',
            self::Refresh => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/>',
            self::Plus => '<path d="M12 5v14M5 12h14"/>',
            self::Logo => '<path d="M9 12h6M13 6l6 6-6 6"/>',
            self::LogIn => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
            self::LogOut => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            self::Lock => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            self::Clock => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
            self::Pencil => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            self::Copy => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
            self::QrCode => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/>',
            self::Shield => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/>',
            self::BarChart => '<path d="M4 19V9M12 19V5M20 19v-7"/>',
            self::Trash => '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m2 0-1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 7"/>',
            self::ChevronDown => '<path d="m6 9 6 6 6-6"/>',
            self::Close => '<path d="M18 6 6 18M6 6l12 12"/>',
            self::AlertCircle => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
            self::CheckCircle => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
            self::Info => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
            self::Key => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/>',
            self::Ban => '<circle cx="12" cy="12" r="9"/><path d="m5.7 5.7 12.6 12.6"/>',
            self::Sliders => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
            self::LayoutGrid => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            self::Users => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 3.1a4 4 0 0 1 0 7.8M22 21a7 7 0 0 0-5-6.7"/>',
            self::ExternalLink => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            self::Link => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        };
    }
}
