/**
 * Created by Eamonn on 31/07/2015.
 */

$(document).ready(function()
{
    $(function() {
        // Setup html5 version
        var uploaderCtn = $('#uploader'),
            consoleCtn = $('#console');

        if(uploaderCtn.length == 0)
            return false;

        uploaderCtn.pluploadQueue({
            // General settings
            runtimes : 'html5,flash,silverlight,html4',
            url : SU_PATH_CORES + "Core.uploader.php",

            chunk_size : '1mb',
            rename : true,
            dragdrop: true,

            filters : {
                // Maximum file size
                max_file_size : '10mb',

                // Specify what files to browse for
                mime_types: [
                    {title : "Image files", extensions : "bmp,gif,jpg,jpeg,png"},
                    {title : "Document files", extensions : "doc,docx,xls,xlsx,pdf,txt,odt,odf"},
                    {title : "Zip files", extensions : "zip"}
                ]
            },

            // Resize images on client-side if we can
            /*resize: {
                width : 1000,
                height : 1000,
                quality : 100,
                crop: false // crop to exact dimensions
            },*/

            // Flash settings
            flash_swf_url : SU_PATH_JS + 'vendor/plupload/Moxie.swf',

            // Silverlight settings
            silverlight_xap_url : SU_PATH_JS + 'vendor/plupload/Moxie.xap',

            multiple_queues: true,
            unique_names: true,

            multipart_params : {
                //"post_id" : idInput.val(),
                //"post_guid" : guidInput.val(),
                //"token" : tokenInput.val(),
                "files" : {}
            }
        });

        var uploader = uploaderCtn.pluploadQueue(),
            int = 0;

        uploader.bind("FilesAdded", function(data)
        {
            var files = data.files;
            $.each(files, function(index, file)
            {
                uploader.settings.multipart_params.files[file.id] = file.name;
            });
        });

        uploader.bind('FileUploaded', function(up, file, response)
        {
            var responseObj = suParseAjaxJSONResponse(response.response);

            if(responseObj.hasOwnProperty('su_success'))
            {
                var feedbackCtn = $('<span/>');
                consoleCtn.append(feedbackCtn);
                if (uploader.files.length == (uploader.total.uploaded + uploader.total.failed))
                    feedbackCtn.text(uploader.total.uploaded + ' uploaded successfully, ' + uploader.total.failed + ' uploads failed.')
                        .delay(3000).fadeOut('slow', function(){ feedbackCtn.remove() });

                try
                {
                    var args = JSON.parse(response.response);

                    if(args.hasOwnProperty('su_success'))
                        refreshAttachments(args.attachments);
                }
                catch(e)
                {
                    var opts = $.extend(true, {}, suDialogOptions);
                    opts.message = '<p>An error occurred while updating the page: ' + e.message + '</p>';
                    opts.message+= '<p>Try saving your work and refreshing the page.</p>';
                }

            }
        });
        //uploader.init();
    });

});