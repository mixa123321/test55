// necessary parameters
// 'imgFileInputId' and html input file (hidden) with this id
// 'imgId' and html img with this id
// 'cancelImgId' and html button with this id
// 'setNullId' and html input hidden with this id
// 'defaultCurrentSrc'
// 'currentSrc'

$(function () {
    const imgFileInput = document.getElementById(imgFileInputId);
    const img = document.getElementById(imgId);
    const cancelImg = document.getElementById(cancelImgId);
    const setNull = document.getElementById(setNullId);

    if (!currentSrc || currentSrc === '')
        currentSrc = defaultCurrentSrc;

    const clearLogo = () => {
        setSrc(defaultCurrentSrc);
        setNull.value = 'TRUE';
    };

    const readImg = () => {
        if (imgFileInput.files && imgFileInput.files[0]) {
            setNull.value = 'FALSE';
            let reader = new FileReader();
            reader.onload = e => setSrc(e.target.result);
            reader.readAsDataURL(imgFileInput.files[0]);
        } else clearLogo();
    };

    const setSrc = (src = currentSrc)=> {
        img.src = src;
        img.dispatchEvent(new Event('change'));
    }

    readImg();

    setSrc();
    setNull.value = 'FALSE';

    img.addEventListener('click', e => {
        e.preventDefault();
        imgFileInput.click();
        $('form :submit').removeAttr("disabled")
    });
    cancelImg.addEventListener('click', e => {
        e.preventDefault();
        setNull.value = 'FALSE';
        imgFileInput.value = '';
        setSrc()
    });
    imgFileInput.addEventListener('click', clearLogo);
    imgFileInput.addEventListener('change', () => readImg())
});