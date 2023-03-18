package fr.ppp.musikfet;

import java.io.IOException;
import java.util.List;

import fr.ppp.musikfet.YtDL.VideoInfo;

public class Musikfet {

	public static void main(String[] args) {
		try {
			YtDL.downloadIfNotExists();
		} catch (IOException e) {
			System.err.println("Could not initialize yt-dl");
			e.printStackTrace();
			return;
		}
		
		try {
			List<VideoInfo> videos = YtDL.searchForVideos("some thing", 5);
			System.out.println(videos);
		} catch (IOException e) {
			e.printStackTrace();
		}
	}
	
}
