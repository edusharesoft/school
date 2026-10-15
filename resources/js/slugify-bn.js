/*
 * slugifyBn("ঢাকা শপ ১২") → "dhaka-shop-12"   (Bangla → English letters for store URLs)
 * keepTrailing = true keeps a trailing "-" while the user is still typing.
 */
(function () {
    var V = { 'অ': 'o', 'আ': 'a', 'ই': 'i', 'ঈ': 'i', 'উ': 'u', 'ঊ': 'u', 'ঋ': 'ri', 'এ': 'e', 'ঐ': 'oi', 'ও': 'o', 'ঔ': 'ou' };
    var S = { 'া': 'a', 'ি': 'i', 'ী': 'i', 'ু': 'u', 'ূ': 'u', 'ৃ': 'ri', 'ে': 'e', 'ৈ': 'oi', 'ো': 'o', 'ৌ': 'ou' };
    var C = {
        'ক': 'k', 'খ': 'kh', 'গ': 'g', 'ঘ': 'gh', 'ঙ': 'ng', 'চ': 'ch', 'ছ': 'chh', 'জ': 'j', 'ঝ': 'jh', 'ঞ': 'n',
        'ট': 't', 'ঠ': 'th', 'ড': 'd', 'ঢ': 'dh', 'ণ': 'n', 'ত': 't', 'থ': 'th', 'দ': 'd', 'ধ': 'dh', 'ন': 'n',
        'প': 'p', 'ফ': 'f', 'ব': 'b', 'ভ': 'bh', 'ম': 'm', 'য': 'j', 'র': 'r', 'ল': 'l', 'শ': 'sh', 'ষ': 'sh',
        'স': 's', 'হ': 'h', 'ড়': 'r', 'ঢ়': 'rh', 'য়': 'y', 'ৎ': 't', 'ং': 'ng', 'ঃ': 'h', 'ঁ': ''
    };
    var D = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
    var HASANTA = '্', NUKTA = '়';

    function isLetter(ch) { return ch !== undefined && (C[ch] !== undefined || V[ch] !== undefined || S[ch] !== undefined || ch === HASANTA || ch === NUKTA); }

    function translit(str) {
        str = (str || '').normalize ? str.normalize('NFC') : str;
        var out = '', chars = Array.from(str), prev;
        for (var i = 0; i < chars.length; i++) {
            var ch = chars[i], next = chars[i + 1];
            if (next === NUKTA && C[ch + NUKTA] !== undefined) { ch = ch + NUKTA; i++; next = chars[i + 1]; }
            if (D[ch] !== undefined) { out += D[ch]; prev = ch; continue; }
            if (V[ch] !== undefined) { out += V[ch]; prev = ch; continue; }
            if (S[ch] !== undefined) { out += S[ch]; prev = ch; continue; }
            if (ch === HASANTA && next === 'য') { i++; prev = 'য'; continue; }   // য-ফলা (ফ্যা → fa)
            if (ch === HASANTA || ch === NUKTA) { prev = ch; continue; }
            if (C[ch] !== undefined) {
                out += C[ch];
                // inherent vowel: only at the start of a word or right after a conjunct (ব|রিশাল, চ|ন্দ্র|বিন্দু), never at the end
                var wordStart = !isLetter(prev), afterConjunct = prev === HASANTA;
                var nextIsLast = C[next] !== undefined && !isLetter(chars[i + 2]);           // আপ|ন → apan
                var nasal = ['ং', 'ঃ', 'ঁ'].indexOf(next) !== -1;                          // রং → rang
                if (['ং', 'ঃ', 'ঁ', 'ৎ'].indexOf(ch) === -1 && (nasal || (C[next] !== undefined && !nasal && (wordStart || afterConjunct || nextIsLast)))) {
                    out += 'a';
                }
                prev = ch;
                continue;
            }
            out += ch;
            prev = ch;
        }
        return out;
    }

    window.slugifyBn = function (value, keepTrailing) {
        var s = translit(value).toLowerCase()
            .replace(/[\s_.]+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-{2,}/g, '-')
            .replace(/^-+/, '');
        if (!keepTrailing) s = s.replace(/-+$/, '');
        return s.slice(0, 60);
    };
})();
